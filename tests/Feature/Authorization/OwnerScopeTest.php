<?php

use App\Models\Concerns\HasOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Owner scope (RB-03, D-076, D-215): prepared now, used by clients, proposals and documents
| in the next slices. Tested on a temporary table (no implicit commit on MariaDB).
*/

class OwnedRecord extends Model
{
    use HasOwner;

    protected $table = 'owned_records';

    protected $guarded = [];
}

beforeEach(function () {
    // Temporary tables live as long as the connection, which the test transactions reuse.
    DB::statement('DROP TEMPORARY TABLE IF EXISTS owned_records');

    Schema::create('owned_records', function (Blueprint $table): void {
        $table->temporary();
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->timestamps();
    });
});

it('RB-03: shows the agent only his own records', function () {
    [$mine, $other] = User::factory()->agent()->count(2)->create();
    OwnedRecord::query()->create(['owner_id' => $mine->id]);
    OwnedRecord::query()->create(['owner_id' => $mine->id]);
    OwnedRecord::query()->create(['owner_id' => $other->id]);

    $visible = OwnedRecord::query()->visibleTo($mine)->get();

    expect($visible)->toHaveCount(2)
        ->and($visible->every(fn (OwnedRecord $r): bool => $r->isOwnedBy($mine)))->toBeTrue();
});

it('shows manager and admin every record', function (string $role) {
    $agent = User::factory()->agent()->create();
    OwnedRecord::query()->create(['owner_id' => $agent->id]);
    OwnedRecord::query()->create(['owner_id' => $agent->id]);

    $user = User::factory()->state(['role' => $role])->create();

    expect(OwnedRecord::query()->visibleTo($user)->count())->toBe(2);
})->with(['manager', 'admin']);

it('RB-05: shows a deactivated user nothing', function () {
    $agent = User::factory()->agent()->inactive()->create();
    OwnedRecord::query()->create(['owner_id' => $agent->id]);

    expect(OwnedRecord::query()->visibleTo($agent)->count())->toBe(0);
});
