<?php

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
| ProposalPolicy (M06 §3, RB-14; M07 RB-08; M01 §4 matrix; D-091, D-374; F4-01).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

it('M01 §4: agents create proposals, admin and manager do not', function () {
    expect(User::factory()->agent()->create()->can('create', Proposal::class))->toBeTrue()
        ->and(User::factory()->manager()->create()->can('create', Proposal::class))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('create', Proposal::class))->toBeFalse()
        ->and(User::factory()->agent()->inactive()->create()->can('create', Proposal::class))->toBeFalse();
});

it('RB-14: the agent sees, changes, deletes and duplicates only his own drafts', function () {
    $agent = User::factory()->agent()->create();
    $mine = Proposal::factory()->ownedBy($agent)->create();
    $other = Proposal::factory()->create();

    expect($agent->can('viewAny', Proposal::class))->toBeTrue()
        ->and($agent->can('view', $mine))->toBeTrue()
        ->and($agent->can('update', $mine))->toBeTrue()
        ->and($agent->can('delete', $mine))->toBeTrue()
        ->and($agent->can('duplicate', $mine))->toBeTrue()
        ->and($agent->can('view', $other))->toBeFalse()
        ->and($agent->can('update', $other))->toBeFalse()
        ->and($agent->can('delete', $other))->toBeFalse()
        ->and($agent->can('duplicate', $other))->toBeFalse();
});

it('M07 RB-08, D-091: outside the draft the agent no longer changes nor deletes, but still duplicates', function (ProposalStatus $status) {
    $agent = User::factory()->agent()->create();
    $proposal = Proposal::factory()->ownedBy($agent)->status($status)->create();

    expect($agent->can('view', $proposal))->toBeTrue()
        ->and($agent->can('update', $proposal))->toBeFalse()
        ->and($agent->can('delete', $proposal))->toBeFalse()
        ->and($agent->can('duplicate', $proposal))->toBeTrue();
})->with([ProposalStatus::Submitted, ProposalStatus::Approved, ProposalStatus::Rejected, ProposalStatus::Cancelled]);

it('M06 §3, D-374: admin and manager consult every proposal and never work on the drafts of the agents', function (string $role) {
    $user = User::factory()->state(['role' => $role])->create();
    $proposal = Proposal::factory()->create();

    expect($user->can('viewAny', Proposal::class))->toBeTrue()
        ->and($user->can('view', $proposal))->toBeTrue()
        ->and($user->can('update', $proposal))->toBeFalse()
        ->and($user->can('delete', $proposal))->toBeFalse()
        ->and($user->can('duplicate', $proposal))->toBeFalse();
})->with(['manager', 'admin']);

it('RB-05 M01: a deactivated agent can do nothing on his own proposals', function () {
    $agent = User::factory()->agent()->create();
    $proposal = Proposal::factory()->ownedBy($agent)->create();
    $agent->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    expect($agent->can('view', $proposal))->toBeFalse()
        ->and($agent->can('update', $proposal))->toBeFalse()
        ->and($agent->can('delete', $proposal))->toBeFalse();
});
