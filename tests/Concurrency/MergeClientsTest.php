<?php

use App\Domain\Clients\Actions\MergeClients;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedPrice;
use App\Models\PoolCapacity;
use App\Models\PriceList;
use App\Models\Season;
use App\Models\StoredFile;
use App\Models\User;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Merge of two clients while the agent saves a draft of the duplicate (D-480, D-484; slice F5, PR F5-07).
| A second database session plays the configurator: it locks the variant like SaveDraft does and changes
| it without committing. The merge must wait for it and move nothing; once the save has committed, the
| merge moves the draft and raises its lock_version, so the configurator still open gets the conflict.
*/

const MERGE_SESSION = 'configurator-save';

beforeEach(function () {
    $default = config('database.default');
    config(['database.connections.'.MERGE_SESSION => config('database.connections.'.(is_string($default) ? $default : 'mariadb'))]);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    Storage::fake(StoredFile::DISK);
    $this->travelTo(Carbon::parse('2027-05-24 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);

    $this->season = Season::query()->where('code', '2027-28')->first() ?? Season::factory()->forYear(2027)->create();
    $priceList = PriceList::factory()->published()->create(['season_id' => $this->season->id]);
    $league = CompetitionSeason::factory()->create(['season_id' => $this->season->id, 'standard_home_matches' => 19]);
    $this->format = LedFormat::query()->where('code', 'FILA_2')->firstOrFail();
    $this->club = Club::factory()->infront()->create(['name' => 'SS Lazio', 'code' => 'LAZIO']);
    ClubEnrollment::query()->create(['competition_season_id' => $league->id, 'club_id' => $this->club->id]);
    LedPrice::query()->create(['price_list_id' => $priceList->id, 'club_id' => $this->club->id, 'led_format_id' => $this->format->id, 'unit_price' => '1000.00']);
    PoolCapacity::query()->create(['season_id' => $this->season->id, 'club_id' => $this->club->id, 'inventory_pool_id' => InventoryPool::query()->where('code', 'FILA_2')->value('id'), 'is_enabled' => true, 'seconds_per_match' => 600]);

    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    $other = DB::connection(MERGE_SESSION);
    while ($other->transactionLevel() > 0) {
        $other->rollBack();
    }
    DB::purge(MERGE_SESSION);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
});

it('D-484: the merge waits for the save of a draft of the duplicate, then moves it', function () {
    $duplicate = Client::factory()->ownedBy($this->agent)->named('Birra Aurora')->create();
    $keep = Client::factory()->ownedBy($this->agent)->named('Birra Aurora S.p.A.')->create();
    $proposal = app(CreateDraft::class)->handle($this->agent, $duplicate);
    app(SaveDraft::class)->handle($proposal, new DraftData(
        clientId: $duplicate->id,
        seasonId: $this->season->id,
        startsOn: $this->season->starts_on->toDateString(),
        endsOn: $this->season->ends_on->toDateString(),
        clubs: [new DraftClubData($this->club->id, ledFormatId: $this->format->id, ledSeconds: 60)],
    ), $proposal->refresh()->lock_version, $this->agent);
    $before = $proposal->refresh()->lock_version;

    $other = DB::connection(MERGE_SESSION);
    $other->beginTransaction();
    $other->select('SELECT id FROM proposals WHERE id = ? FOR UPDATE', [$proposal->id]);
    $other->table('proposals')->where('id', $proposal->id)->update(['lock_version' => $before + 1]);

    expect(fn () => app(MergeClients::class)->handle($duplicate, $keep, $this->admin))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($proposal->refresh()->client_id)->toBe($duplicate->id)
        ->and($duplicate->refresh()->merged_into_id)->toBeNull();

    $other->commit();

    app(MergeClients::class)->handle($duplicate, $keep, $this->admin);

    expect($proposal->refresh()->client_id)->toBe($keep->id)
        ->and($proposal->lock_version)->toBe($before + 2)
        ->and($duplicate->refresh()->merged_into_id)->toBe($keep->id);
});
