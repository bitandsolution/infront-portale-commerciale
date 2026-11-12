<?php

use App\Domain\Proposals\Actions\ApproveGroup;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\InventoryCommitment;
use App\Models\PartnershipCapacity;
use App\Models\PartnershipPrice;
use App\Models\PartnershipTier;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Factories\InventoryCommitmentFactory;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Two approvals on the same resources (M07 §9, "vince la prima approvata"; D-192, D-216; CLAUDE.md
| §11: concurrency test on the approval; DM-03b).
|
| A second database session plays the other approver: it locks the capacity row of the last contract
| of the tier Top, as ApproveGroup does, and writes its commitment without committing. The approval
| of the first session must wait for it (here: a lock wait of 1 second, repeated by the transaction,
| then the error) and write nothing; once the other one has committed, it must see the commitment
| and refuse to go beyond the residual.
*/

const APPROVAL_SESSION = 'other-approver';

beforeEach(function () {
    $default = config('database.default');
    config(['database.connections.'.APPROVAL_SESSION => config('database.connections.'.(is_string($default) ? $default : 'mariadb'))]);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->tier = PartnershipTier::query()->where('code', 'TOP')->firstOrFail();
    $this->club = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
    ClubEnrollment::query()->create(['competition_season_id' => $league->id, 'club_id' => $this->club->id]);
    PartnershipPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->club->id, 'partnership_tier_id' => $this->tier->id, 'season_price' => '155680.00']);
    $this->capacity = PartnershipCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'partnership_tier_id' => $this->tier->id, 'is_enabled' => true, 'quantity' => 1]);

    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    $other = DB::connection(APPROVAL_SESSION);
    while ($other->transactionLevel() > 0) {
        $other->rollBack();
    }
    DB::purge(APPROVAL_SESSION);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
});

/** A group of one variant with the tier Top of the club, sent for approval. */
function concurrentGroup(object $test): ProposalGroup
{
    $client = Client::factory()->ownedBy($test->agent)->create();
    $proposal = app(CreateDraft::class)->handle($test->agent, $client);

    app(SaveDraft::class)->handle($proposal, new DraftData(
        clientId: $client->id,
        seasonId: $test->season->id,
        startsOn: $test->season->starts_on->toDateString(),
        endsOn: $test->season->ends_on->toDateString(),
        clubs: [new DraftClubData($test->club->id, tierId: $test->tier->id)],
    ), $proposal->refresh()->lock_version, $test->agent);

    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);
    app(SubmitGroup::class)->handle($group, $test->agent);

    return $group;
}

/**
 * The other approver: transaction open, capacity of the tier locked and the last contract committed
 * to another proposal, not committed yet.
 */
function otherApproverSession(object $test): Connection
{
    $row = InventoryCommitmentFactory::proposalRow($test->season, $test->club);
    $other = DB::connection(APPROVAL_SESSION);
    $other->beginTransaction();
    $other->select('SELECT id FROM partnership_capacities WHERE id = ? FOR UPDATE', [$test->capacity->id]);
    $other->table('inventory_commitments')->insert([
        'season_id' => $test->season->id,
        'club_id' => $test->club->id,
        'proposal_id' => $row->version->proposal_id,
        'proposal_version_id' => $row->proposal_version_id,
        'proposal_version_club_id' => $row->id,
        'resource_type' => 'partnership',
        'partnership_tier_id' => $test->tier->id,
        'quantity' => 1,
        'starts_on' => $test->season->starts_on->toDateString(),
        'ends_on' => $test->season->ends_on->toDateString(),
        'is_forced' => false,
        'created_by' => $test->admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $other;
}

it('D-216: the approval waits while another approval holds the capacity, and writes nothing', function () {
    $group = concurrentGroup($this);
    otherApproverSession($this);

    expect(fn () => app(ApproveGroup::class)->handle($group, [], $this->admin))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect(Proposal::query()->where('proposal_group_id', $group->id)->value('status'))->toBe(ProposalStatus::Submitted->value)
        ->and(InventoryCommitment::query()->count())->toBe(0);
});

it('M07 §9: of two approvals on the last contract the first one wins, the second is blocked', function () {
    $group = concurrentGroup($this);
    $other = otherApproverSession($this);

    expect(fn () => app(ApproveGroup::class)->handle($group, [], $this->admin))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    $other->commit();

    expect(fn () => app(ApproveGroup::class)->handle($group, [], $this->admin))
        ->toThrow(ProposalsActionNotAllowed::class, 'Approvazione bloccata');

    expect(Proposal::query()->where('proposal_group_id', $group->id)->value('status'))->toBe(ProposalStatus::Submitted->value)
        ->and(InventoryCommitment::query()->count())->toBe(1);
});
