<?php

use App\Domain\Proposals\Actions\ApproveGroup;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Actions\SaveNewVersion;
use App\Domain\Proposals\Actions\StartRevision;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\InventoryCommitment;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PoolCapacity;
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
| A new version of an approved variant and another approval on the same resource (M07 FL-04, §9; D-216,
| D-465; slice F5, PR F5-04). A second database session plays the other approver: it locks the
| capacity row of the 2nd row of the club and commits 8′ to another proposal without committing. The
| new version (1′ → 3′) must wait for it and write nothing; once the other one has committed it must
| see the commitment and refuse the increase beyond the residual (forceable only by the admin).
*/

const VERSION_SESSION = 'other-version-approver';

beforeEach(function () {
    $default = config('database.default');
    config(['database.connections.'.VERSION_SESSION => config('database.connections.'.(is_string($default) ? $default : 'mariadb'))]);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $this->pool = InventoryPool::query()->where('code', 'FILA_2')->firstOrFail();
    $this->club = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
    ClubEnrollment::query()->create(['competition_season_id' => $league->id, 'club_id' => $this->club->id]);
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->club->id, 'led_format_id' => $this->format->id, 'unit_price' => '1000.00']);
    $this->capacity = PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'inventory_pool_id' => $this->pool->id, 'is_enabled' => true, 'seconds_per_match' => 600]);

    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    $other = DB::connection(VERSION_SESSION);
    while ($other->transactionLevel() > 0) {
        $other->rollBack();
    }
    DB::purge(VERSION_SESSION);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
});

function versionClubs(object $test, int $seconds): array
{
    return [new DraftClubData($test->club->id, ledFormatId: $test->format->id, ledSeconds: $seconds)];
}

/** A proposal of the club with 1′ of 2nd row, approved, with its working copy changed to 3′. */
function versionUnderRevision(object $test): Proposal
{
    $client = Client::factory()->ownedBy($test->agent)->create();
    $proposal = app(CreateDraft::class)->handle($test->agent, $client);
    $data = fn (int $seconds): DraftData => new DraftData(
        clientId: $client->id,
        seasonId: $test->season->id,
        startsOn: $test->season->starts_on->toDateString(),
        endsOn: $test->season->ends_on->toDateString(),
        clubs: versionClubs($test, $seconds),
    );

    app(SaveDraft::class)->handle($proposal, $data(60), $proposal->refresh()->lock_version, $test->agent);
    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);
    app(SubmitGroup::class)->handle($group, $test->agent);
    app(ApproveGroup::class)->handle($group, [], $test->admin);
    app(StartRevision::class)->handle($proposal->refresh(), $test->admin);
    app(SaveDraft::class)->review($proposal, $data(180), $proposal->refresh()->lock_version, $test->admin);

    return $proposal->refresh();
}

/** The other approver: capacity of the 2nd row locked and 8′ committed to another proposal, not committed yet. */
function otherVersionSession(object $test): Connection
{
    $row = InventoryCommitmentFactory::proposalRow($test->season, $test->club);
    $other = DB::connection(VERSION_SESSION);
    $other->beginTransaction();
    $other->select('SELECT id FROM pool_capacities WHERE id = ? FOR UPDATE', [$test->capacity->id]);
    $other->table('inventory_commitments')->insert([
        'season_id' => $test->season->id,
        'club_id' => $test->club->id,
        'proposal_id' => $row->version->proposal_id,
        'proposal_version_id' => $row->proposal_version_id,
        'proposal_version_club_id' => $row->id,
        'resource_type' => 'pool',
        'inventory_pool_id' => $test->pool->id,
        'quantity' => 480,
        'starts_on' => $test->season->starts_on->toDateString(),
        'ends_on' => $test->season->ends_on->toDateString(),
        'is_forced' => false,
        'created_by' => $test->admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $other;
}

it('D-216, D-465: the new version waits for the other approval, then refuses the increase beyond the residual', function () {
    $proposal = versionUnderRevision($this);
    $other = otherVersionSession($this);

    expect(fn () => app(SaveNewVersion::class)->handle($proposal, $proposal->lock_version, new DiscountInput, $this->admin))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($proposal->refresh()->currentVersion?->number)->toBe(1)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->whereNull('released_at')->sum('quantity'))->toEqual(60);

    $other->commit();

    // 600 − 480 = 120″ for the copy: 3′ are an increase beyond the residual.
    expect(fn () => app(SaveNewVersion::class)->handle($proposal->refresh(), $proposal->lock_version, new DiscountInput, $this->admin))
        ->toThrow(ProposalsActionNotAllowed::class, 'Approvazione bloccata');

    $version = app(SaveNewVersion::class)->handle($proposal->refresh(), $proposal->lock_version, new DiscountInput, $this->admin, 'Accordo del direttore commerciale');

    expect($version->number)->toBe(2)
        ->and($version->is_forced)->toBeTrue()
        ->and(InventoryCommitment::query()->where('proposal_version_id', $version->id)->value('quantity'))->toBe(180);
});
