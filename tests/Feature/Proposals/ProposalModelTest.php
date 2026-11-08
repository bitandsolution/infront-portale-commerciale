<?php

use App\Domain\Proposals\Exceptions\ProposalImmutable;
use App\Domain\Proposals\Services\ProposalNumbers;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Models\HospitalityVariant;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
| Models of the proposals (D-198, D-199, D-371, D-376; F4-01): first line of defence of the frozen
| versions, owner scope on agent_id, numbers from the sequence.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

/**
 * A draft with its working version, one club row and one hospitality row.
 */
function proposalsModelDraft(?User $agent = null): ProposalVersionClubSeat
{
    $proposal = Proposal::factory()->ownedBy($agent ?? User::factory()->agent()->create())->withWorkingVersion()->create();
    $version = $proposal->workingVersion;
    assert($version instanceof ProposalVersion);
    $row = ProposalVersionClub::factory()->create(['proposal_version_id' => $version->id]);

    return ProposalVersionClubSeat::factory()->create(['proposal_version_club_id' => $row->id]);
}

function proposalsModelFreeze(ProposalVersion $version): void
{
    $version->update(['number' => 1, 'frozen_at' => now(), 'frozen_by' => User::factory()->admin()->create()->id]);
}

it('D-371: numbers come from the sequence, from 204 upwards, one after the other', function () {
    $numbers = app(ProposalNumbers::class);
    $first = $numbers->next();

    expect($first)->toBeGreaterThanOrEqual(204)
        ->and($numbers->next())->toBe($first + 1);
});

it('D-375: a new proposal is a draft with its working version', function () {
    $proposal = Proposal::factory()->withWorkingVersion()->create();

    expect($proposal->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->isDraft())->toBeTrue()
        ->and($proposal->number)->toBeGreaterThanOrEqual(204)
        ->and($proposal->workingVersion?->number)->toBeNull()
        ->and($proposal->workingVersion?->isFrozen())->toBeFalse()
        ->and($proposal->workingVersion?->starts_on?->toDateString())->toBe($proposal->season?->starts_on->toDateString())
        ->and($proposal->client?->owner_id)->toBe($proposal->agent_id);
});

it('D-198: a working version is frozen once, then the model refuses every change and the deletion', function () {
    $seat = proposalsModelDraft();
    $version = $seat->clubRow->version;

    proposalsModelFreeze($version);

    expect($version->fresh()?->isFrozen())->toBeTrue()
        ->and(fn () => $version->update(['change_note' => 'Nota']))->toThrow(ProposalImmutable::class)
        ->and(fn () => $version->delete())->toThrow(ProposalImmutable::class);
});

it('M03-CA6, D-198: the model refuses to change, delete or add rows and seats of a frozen version', function () {
    $seat = proposalsModelDraft();
    $row = $seat->clubRow;
    proposalsModelFreeze($row->version);

    expect(fn () => $row->update(['club_total' => '10.00']))->toThrow(ProposalImmutable::class)
        ->and(fn () => $row->delete())->toThrow(ProposalImmutable::class)
        ->and(fn () => ProposalVersionClub::factory()->create(['proposal_version_id' => $row->proposal_version_id]))->toThrow(ProposalImmutable::class)
        ->and(fn () => $seat->update(['seats' => 3]))->toThrow(ProposalImmutable::class)
        ->and(fn () => $seat->delete())->toThrow(ProposalImmutable::class)
        ->and(fn () => ProposalVersionClubSeat::factory()->create([
            'proposal_version_club_id' => $row->id,
            'hospitality_variant_id' => HospitalityVariant::factory()->create()->id,
        ]))->toThrow(ProposalImmutable::class);
});

it('D-198: the model refuses to move a row of a working version into a frozen version', function () {
    $frozen = proposalsModelDraft()->clubRow;
    proposalsModelFreeze($frozen->version);
    $working = proposalsModelDraft()->clubRow;

    expect(fn () => $working->update(['proposal_version_id' => $frozen->proposal_version_id]))->toThrow(ProposalImmutable::class);
});

it('D-165: rows and seats of a working version are saved and deleted freely', function () {
    $seat = proposalsModelDraft();
    $row = $seat->clubRow;

    $row->update(['club_total' => '1500.00']);
    $seat->update(['seats' => 6]);
    $seat->delete();

    expect($row->fresh()?->club_total)->toBe('1500.00')
        ->and($row->seats()->count())->toBe(0);
});

it('D-199: the model refuses to change or delete an entry of the history', function () {
    $proposal = Proposal::factory()->create();
    $event = ProposalEvent::query()->create(['proposal_id' => $proposal->id, 'type' => ProposalEventType::Created, 'user_id' => $proposal->agent_id]);

    expect($event->type)->toBe(ProposalEventType::Created)
        ->and(fn () => $event->update(['notes' => 'Modificata']))->toThrow(ProposalImmutable::class)
        ->and(fn () => $event->delete())->toThrow(ProposalImmutable::class);
});

it('M07 RB-08: deleting a draft removes its working version and rows', function () {
    $seat = proposalsModelDraft();
    $proposal = $seat->clubRow->version->proposal;

    $proposal->delete();

    expect(ProposalVersion::query()->where('proposal_id', $proposal->id)->exists())->toBeFalse()
        ->and(ProposalVersionClubSeat::query()->whereKey($seat->id)->exists())->toBeFalse();
});

it('RB-14, D-376: the agent sees only his own proposals, through agent_id', function () {
    [$mine, $other] = User::factory()->agent()->count(2)->create();
    Proposal::factory()->ownedBy($mine)->count(2)->create();
    Proposal::factory()->ownedBy($other)->create();

    $visible = Proposal::query()->visibleTo($mine)->get();

    expect($visible)->toHaveCount(2)
        ->and($visible->every(fn (Proposal $p): bool => $p->isOwnedBy($mine) && $p->owner->is($mine) && $p->agent->is($mine)))->toBeTrue();
});

it('D-376: manager and admin see every proposal, a deactivated user none', function () {
    $agent = User::factory()->agent()->create();
    Proposal::factory()->ownedBy($agent)->count(2)->create();

    expect(Proposal::query()->visibleTo(User::factory()->manager()->create())->count())->toBe(2)
        ->and(Proposal::query()->visibleTo(User::factory()->admin()->create())->count())->toBe(2)
        ->and(Proposal::query()->visibleTo(User::factory()->agent()->inactive()->create())->count())->toBe(0);
});
