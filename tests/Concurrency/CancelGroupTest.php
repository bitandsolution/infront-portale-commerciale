<?php

use App\Domain\Proposals\Actions\ApproveGroup;
use App\Domain\Proposals\Actions\CancelGroup;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Enums\CommitmentReleaseReason;
use App\Enums\ProposalStatus;
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
use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| Cancellation of an approved group while another action holds the group (M07 FL-05, §9; D-216, D-469,
| D-471; slice F5, PR F5-05). A second database session plays the other admin or manager: it locks the
| group and its variant like the client choice or the new version do, and changes the variant without
| committing. The cancellation must wait for it and write nothing; once the other one has committed,
| it reads the variant again and cancels it, releasing every open commitment.
*/

const CANCEL_SESSION = 'other-group-holder';

beforeEach(function () {
    $default = config('database.default');
    config(['database.connections.'.CANCEL_SESSION => config('database.connections.'.(is_string($default) ? $default : 'mariadb'))]);
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
    $other = DB::connection(CANCEL_SESSION);
    while ($other->transactionLevel() > 0) {
        $other->rollBack();
    }
    DB::purge(CANCEL_SESSION);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
});

/** A proposal of the club with 1′ of 2nd row, sent and approved. */
function cancelApproved(object $test): Proposal
{
    $client = Client::factory()->ownedBy($test->agent)->create();
    $proposal = app(CreateDraft::class)->handle($test->agent, $client);
    $data = new DraftData(
        clientId: $client->id,
        seasonId: $test->season->id,
        startsOn: $test->season->starts_on->toDateString(),
        endsOn: $test->season->ends_on->toDateString(),
        clubs: [new DraftClubData($test->club->id, ledFormatId: $test->format->id, ledSeconds: 60)],
    );

    app(SaveDraft::class)->handle($proposal, $data, $proposal->refresh()->lock_version, $test->agent);
    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);
    app(SubmitGroup::class)->handle($group, $test->agent);
    app(ApproveGroup::class)->handle($group, [], $test->admin);

    return $proposal->refresh();
}

it('D-216: the cancellation waits for the action that holds the group, then cancels and releases', function () {
    $proposal = cancelApproved($this);
    $group = ProposalGroup::query()->findOrFail($proposal->proposal_group_id);

    $other = DB::connection(CANCEL_SESSION);
    $other->beginTransaction();
    $other->select('SELECT id FROM proposal_groups WHERE id = ? FOR UPDATE', [$group->id]);
    $other->select('SELECT id FROM proposals WHERE id = ? FOR UPDATE', [$proposal->id]);
    $other->table('proposals')->where('id', $proposal->id)->update(['lock_version' => $proposal->lock_version + 1]);

    expect(fn () => app(CancelGroup::class)->handle($group, 'Il cliente ha ritirato il budget', $this->admin))
        ->toThrow(QueryException::class, 'Lock wait timeout');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->whereNull('released_at')->count())->toBe(1);

    $other->commit();

    $cancelled = app(CancelGroup::class)->handle($group, 'Il cliente ha ritirato il budget', $this->admin);

    expect($cancelled)->toHaveCount(1)
        ->and($proposal->refresh()->status)->toBe(ProposalStatus::Cancelled)
        ->and($proposal->lock_version)->toBe($cancelled[0]->lock_version)
        ->and(InventoryCommitment::query()->where('proposal_id', $proposal->id)->sole()->release_reason)->toBe(CommitmentReleaseReason::Cancelled);
});
