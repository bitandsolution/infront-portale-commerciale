<?php

use App\Enums\CalendarImportStatus;
use App\Enums\DocumentLayout;
use App\Enums\LedPhotoKind;
use App\Enums\PriceListStatus;
use App\Enums\ProposalStatus;
use App\Enums\SeasonStatus;
use App\Jobs\GenerateDocument;
use App\Models\AuditEvent;
use App\Models\CalendarImport;
use App\Models\Client;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\LedMockupSetting;
use App\Models\PriceList;
use App\Models\Proposal;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalVersion;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Demo\DemoArtwork;
use Database\Seeders\DevelopmentUsersSeeder;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
| Data of the demo of 4/12/2026 (D-400, D-438…D-442; slice DM, PR DM-08): the development command
| demo:prepare, on a database just seeded, writes through the Actions of the portal the season, the
| Serie A with the fictitious calendar, price list, capacities, clients, slide library, LED
| simulations and the three groups of proposals of D-440. Expected amounts computed with python3 and
| decimal on the fictitious calendar (Roma: 19 home matches, 10 from 01/01/2028).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-12-04 10:00', 'Europe/Rome'));
    $this->seed(SystemDataSeeder::class);
    $this->seed(DevelopmentUsersSeeder::class);
    $this->admin = User::factory()->admin()->create();
    config(['documents.demo_assets_path' => null]);
});

/** Runs the command and returns exit code and output. */
function demoRun(): array
{
    $code = Artisan::call('demo:prepare');

    return [$code, Artisan::output()];
}

/** The working or last version of a proposal. */
function demoVersion(Proposal $proposal): ProposalVersion
{
    return ProposalVersion::query()->where('proposal_id', $proposal->id)->orderByDesc('id')->firstOrFail();
}

it('D-400: demo:prepare prepares season, clubs, calendar, catalog, clients, slides and LED simulations', function () {
    [$code, $output] = demoRun();

    expect($code)->toBe(0)->and($output)->toContain('Dati della demo pronti');

    $season = Season::query()->where('code', '2027-28')->firstOrFail();
    expect($season->status)->toBe(SeasonStatus::Open);

    $serieA = CompetitionSeason::query()->whereHas('competition', fn ($query) => $query->where('code', 'SERIE_A'))->firstOrFail();
    $serieB = CompetitionSeason::query()->whereHas('competition', fn ($query) => $query->where('code', 'SERIE_B'))->firstOrFail();

    // D-441: Serie B with the standard number, no clubs and no calendar.
    expect(ClubEnrollment::query()->where('competition_season_id', $serieA->id)->count())->toBe(20)
        ->and(ClubEnrollment::query()->where('competition_season_id', $serieB->id)->count())->toBe(0)
        ->and($serieB->standard_home_matches)->toEqual(19)
        ->and(Club::query()->where('is_infront', true)->orderBy('code')->pluck('code')->all())->toBe(['BOLOGNA', 'COMO', 'LAZIO', 'ROMA', 'UDINESE'])
        ->and(Club::query()->where('is_top_match_opponent', true)->orderBy('code')->pluck('code')->all())->toBe(['INTER', 'JUVENTUS', 'LAZIO', 'MILAN', 'NAPOLI', 'ROMA'])
        ->and(Club::query()->where('is_infront', true)->whereNull('logo_file_id')->count())->toBe(0);

    // 380 matches from a confirmed import (calendar loaded, D-187), 19 at home for every club.
    expect(FixtureMatch::query()->count())->toBe(380)
        ->and($serieA->calendar_loaded_at)->not->toBeNull()
        ->and(CalendarImport::query()->where('status', CalendarImportStatus::Confirmed->value)->count())->toBe(1);

    foreach (Club::all() as $club) {
        expect(FixtureMatch::query()->where('home_club_id', $club->id)->count())->toBe(19);
    }

    expect(PriceList::query()->where('season_id', $season->id)->value('status'))->toBe(PriceListStatus::Published)
        ->and(Client::query()->count())->toBe(5)
        ->and(Client::query()->where('owner_id', User::query()->where('email', 'agente1@example.test')->value('id'))->count())->toBe(4);

    // Slides: 12 of the network and 8 for each of the five clubs (D-405, D-428).
    expect(Slide::query()->where('scope', 'infront')->count())->toBe(12)
        ->and(Slide::query()->where('scope', 'club')->count())->toBe(40)
        ->and(Slide::query()->where('layout_code', DocumentLayout::CustomBlocks->value)->count())->toBe(6)
        ->and(Slide::query()->where('layout_code', DocumentLayout::LedMockup->value)->whereNull('image_file_id')->count())->toBe(6)
        ->and(Slide::query()->whereNull('image_file_id')->count())->toBe(6);

    // Without the folder of the demo: only the three generic Infront photos, drawn.
    expect(LedMockupSetting::query()->whereNull('club_id')->count())->toBe(3)
        ->and(LedMockupSetting::query()->whereNotNull('club_id')->count())->toBe(0)
        ->and($output)->toContain('UDINESE_FILA1 assente');

    // Everything written through the Actions: audit events of the admin and of the agent.
    expect(AuditEvent::query()->where('action', 'calendar_import.confirmed')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'slide.created')->count())->toBe(52);
});

it('D-440: demo:prepare prepares the three groups of proposals of the demo', function () {
    demoRun();

    $agent = User::query()->where('email', 'agente1@example.test')->firstOrFail();
    $byClient = fn (string $name) => Proposal::query()
        ->whereHas('client', fn ($query) => $query->where('company_name', $name))
        ->orderBy('variant_letter')
        ->get();

    // (a) A, B, C in approval.
    $aurora = $byClient('Birra Aurora S.p.A.');
    expect($aurora->pluck('variant_letter')->all())->toBe(['A', 'B', 'C'])
        ->and($aurora->pluck('status')->unique()->all())->toBe([ProposalStatus::Submitted])
        ->and($aurora->pluck('agent_id')->unique()->all())->toBe([$agent->id])
        ->and($aurora->pluck('proposal_group_id')->unique()->count())->toBe(1)
        ->and(demoVersion($aurora[0])->list_total)->toBe('307000.00')
        ->and(demoVersion($aurora[2])->clubs()->count())->toBe(5);

    // (b) The dates: Roma Premium, 2nd row 1′, 2 Premium seats; 19 home matches from the start of
    // the season, 10 from 01/01/2028: 135,000 + 2,400 × 19 + 7,000 and 135,000 + 2,400 × 10 + 7,000.
    $vela = $byClient('Vela Assicurazioni S.p.A.');
    expect($vela->pluck('status')->unique()->all())->toBe([ProposalStatus::Draft])
        ->and(demoVersion($vela[0])->starts_on->toDateString())->toBe('2027-07-01')
        ->and(demoVersion($vela[0])->list_total)->toBe('187600.00')
        ->and(demoVersion($vela[1])->starts_on->toDateString())->toBe('2028-01-01')
        ->and(demoVersion($vela[1])->list_total)->toBe('166000.00');

    // (c) Udinese approved with 10%: Top 137,500 + Jumbo 1′ 2,970 × 19 + 4 × 1,925 = 201,630.00.
    $monteverde = $byClient('Caffè Monteverde S.r.l.');
    $frozen = demoVersion($monteverde[0]);
    expect($monteverde[0]->status)->toBe(ProposalStatus::Approved)
        ->and($frozen->number)->toEqual(1)
        ->and($frozen->list_total)->toBe('201630.00')
        ->and($frozen->discount_amount)->toBe('20163.00')
        ->and($frozen->final_total)->toBe('181467.00')
        ->and(ProposalCustomSlide::query()->where('proposal_group_id', $monteverde[0]->proposal_group_id)->count())->toBe(1);

    Queue::assertPushed(GenerateDocument::class, 1);
});

it('D-438, D-439: demo:prepare takes photos, logos and slides from the folder of the demo', function () {
    $folder = sys_get_temp_dir().'/demo-assets-'.uniqid();
    File::ensureDirectoryExists($folder.'/campi');
    File::ensureDirectoryExists($folder.'/loghi');
    File::ensureDirectoryExists($folder.'/slide');
    $artwork = new DemoArtwork;

    foreach (['UDINESE_FILA1', 'UDINESE_FILA2', 'udinese_jumbo_domination', 'INFRONT_FILA1'] as $name) {
        file_put_contents($folder.'/campi/'.$name.'.jpg', $artwork->fieldPhoto());
    }

    file_put_contents($folder.'/loghi/LAZIO.png', $artwork->clubLogo('LAZ', [135, 216, 247]));
    file_put_contents($folder.'/slide/INFRONT_COPERTINA.png', $artwork->slide('Copertina vera', 'Prova', [0, 0, 0]));
    file_put_contents($folder.'/slide/LAZIO_RECAP.png', $artwork->clubLogo('LAZ', [135, 216, 247]));
    config(['documents.demo_assets_path' => $folder]);

    [$code, $output] = demoRun();
    File::deleteDirectory($folder);

    expect($code)->toBe(0)
        ->and($output)->toContain('Logo vero del club Lazio')
        ->and($output)->toContain('Logo disegnato del club Roma')
        ->and($output)->toContain('1 sostituite dalla cartella della demo')
        ->and($output)->toContain('ignorato perché non è un\'immagine 1920×1080: LAZIO_RECAP.png')
        ->and($output)->toContain('foto INFRONT_FILA1 dalla cartella della demo')
        ->and($output)->toContain('foto INFRONT_FILA2 disegnata');

    $udinese = Club::query()->where('code', 'UDINESE')->firstOrFail();
    expect(LedMockupSetting::query()->where('club_id', $udinese->id)->get()->map(fn (LedMockupSetting $setting): string => $setting->photo_kind->value)->sort()->values()->all())
        ->toBe(['both_rows', 'row_1', 'row_2']);

    // The Udinese rows measured on its photos: 1st row = lower (101–139), both rows upper then lower.
    $both = LedMockupSetting::query()->where('club_id', $udinese->id)->where('photo_kind', LedPhotoKind::BothRows->value)->firstOrFail();
    $row1 = LedMockupSetting::query()->where('club_id', $udinese->id)->where('photo_kind', LedPhotoKind::Row1->value)->firstOrFail();
    expect($row1->areas)->toBe([[[0, 101], [1768, 101], [1768, 139], [0, 139]]])
        ->and($both->areas)->toBe([[[0, 61], [1768, 61], [1768, 98], [0, 98]], [[0, 101], [1768, 101], [1768, 139], [0, 139]]]);
});

it('D-400: demo:prepare refuses a portal with data or without an admin', function () {
    Season::factory()->forYear(2027)->create();

    [$code, $output] = demoRun();
    expect($code)->toBe(1)->and($output)->toContain('migrate:fresh --seed')
        ->and(Club::query()->count())->toBe(0);

    Season::query()->delete();
    $this->admin->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    [$code, $output] = demoRun();
    expect($code)->toBe(1)->and($output)->toContain('users:create-admin');
});
