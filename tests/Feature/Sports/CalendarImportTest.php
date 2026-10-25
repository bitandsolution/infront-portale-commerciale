<?php

use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\AliasSource;
use App\Enums\AuditAction;
use App\Enums\CalendarImportStatus;
use App\Enums\MatchSource;
use App\Enums\MatchStatus;
use App\Models\CalendarImport;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\ClubEnrollment;
use App\Models\CompetitionSeason;
use App\Models\FixtureMatch;
use App\Models\Season;
use App\Models\Stadium;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Navigation\PortalPages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Calendar import (M02 F-09, F-10, FL-02, §8; M02-CA1, CA2, CA3, CA8; D-319, D-321, D-322, D-325).
| Files are real .xlsx written with OpenSpout (calendarFile()). Permissions of every route are in
| the route × role matrix (RouteMatrixTest).
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
});

const IMPORT_HEADER = ['Stagione', 'Competizione', 'Giornata', 'Data', 'Ora', 'Squadra casa', 'Squadra ospite', 'Stadio'];

/**
 * A 2051-52 edition with the given number of enrolled clubs ("Prova 01", "Prova 02"…, each with a
 * home stadium "Stadio Prova NN"), selected in the session of the top bar.
 *
 * @return array{0: CompetitionSeason, 1: list<Club>}
 */
function importsEdition(int $teams = 4, bool $closed = false): array
{
    $season = Season::factory()->forYear(2051)->state(['status' => $closed ? 'closed' : 'open'])->create();
    $edition = CompetitionSeason::factory()->for($season)->create();
    $clubs = [];

    for ($number = 1; $number <= $teams; $number++) {
        $label = sprintf('%02d', $number);
        $stadium = Stadium::factory()->create(['name' => 'Stadio Prova '.$label]);
        $club = Club::factory()->create(['name' => 'Società Prova '.$label, 'short_name' => 'Prova '.$label, 'home_stadium_id' => $stadium->id]);
        ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $club->id]);
        $clubs[] = $club;
    }

    session([CurrentSeason::SESSION_KEY => $season->id]);

    return [$edition, $clubs];
}

/**
 * A row of the layout D-017 for an edition of importsEdition().
 *
 * @return list<string|int|float|null>
 */
function importRow(int $matchday, string $date, string $home, string $away, ?string $time = '20:45', string $stadium = '', string $season = '2051-52', string $competition = ''): array
{
    return [$season, $competition, $matchday, $date, $time, $home, $away, $stadium];
}

/**
 * Double round robin of the clubs (circle method): every club plays every other one at home and
 * away, one matchday a week from Sunday 3 September 2051. 20 clubs → 38 matchdays, 380 matches.
 *
 * @param  list<Club>  $clubs
 * @return list<list<string|int|float|null>>
 */
function importRoundRobin(array $clubs): array
{
    $teams = array_map(fn (Club $club): string => $club->short_name, $clubs);
    $count = count($teams);
    $rounds = $count - 1;
    $rows = [];
    $first = Carbon::create(2051, 9, 3);

    for ($round = 0; $round < $rounds; $round++) {
        for ($index = 0; $index < intdiv($count, 2); $index++) {
            $a = $teams[$index];
            $b = $teams[$count - 1 - $index];
            [$home, $away] = ($round + $index) % 2 === 0 ? [$a, $b] : [$b, $a];
            $rows[] = importRow($round + 1, $first->copy()->addWeeks($round)->format('d/m/Y'), $home, $away);
            $rows[] = importRow($round + 1 + $rounds, $first->copy()->addWeeks($round + $rounds)->format('d/m/Y'), $away, $home);
        }

        // Circle method: the first team stays, the others turn by one place.
        $teams = array_merge([$teams[0], $teams[$count - 1]], array_slice($teams, 1, $count - 2));
    }

    return $rows;
}

/**
 * Uploads a file for the edition and returns the import created.
 *
 * @param  list<list<string|int|float|null>>  $rows  without the header
 * @param  list<string>|null  $header
 */
function importUpload(CompetitionSeason $edition, array $rows, ?User $user = null, ?array $header = null): CalendarImport
{
    test()->actingAs($user ?? User::factory()->manager()->create())
        ->post(route('sports.imports.store'), [
            'competition_season_id' => $edition->id,
            'file' => calendarFile(array_merge([$header ?? IMPORT_HEADER], $rows)),
        ])
        ->assertRedirect();

    return CalendarImport::query()->latest('id')->firstOrFail();
}

/**
 * The preview row of a line of the file.
 *
 * @return array<string, mixed>
 */
function importPreviewLine(CalendarImport $import, int $line): array
{
    $import->refresh();

    foreach ($import->previewData()->rows as $row) {
        if ($row['line'] === $line) {
            return $row;
        }
    }

    throw new RuntimeException("Line {$line} not in the preview");
}

it('M02-CA1: a Serie A file with 380 matches is imported with a preview without errors; the matches are listed by club', function () {
    [$edition, $clubs] = importsEdition(20);
    $manager = User::factory()->manager()->create();

    $import = importUpload($edition, importRoundRobin($clubs), $manager);

    expect($import->status)->toBe(CalendarImportStatus::Preview)
        ->and($import->previewData()->counts())->toMatchArray(['new' => 380, 'update' => 0, 'unchanged' => 0, 'error' => 0])
        ->and($import->previewData()->unknownNames)->toBe([])
        ->and(FixtureMatch::query()->count())->toBe(0);

    $this->actingAs($manager)
        ->get(route('sports.imports.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Imports/Show')
            ->where('counts.new', 380)
            ->where('counts.error', 0)
            ->has('rows', 380)
            ->where('missingColumns', [])
            ->where('enrollmentsUrl', null));

    $this->actingAs($manager)
        ->post(route('sports.imports.confirm', $import))
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Calendario importato: 380 partite inserite, 0 aggiornate, 0 righe scartate');

    $import->refresh();
    expect($import->status)->toBe(CalendarImportStatus::Confirmed)
        ->and($import->inserted_count)->toBe(380)
        ->and($import->confirmed_by)->toBe($manager->id)
        ->and($edition->fresh()?->calendar_loaded_at)->not->toBeNull()
        ->and(FixtureMatch::query()->count())->toBe(380)
        ->and(FixtureMatch::query()->where('source', MatchSource::Import->value)->where('last_import_id', $import->id)->count())->toBe(380);

    // Every club plays 19 matches at home and 19 away, with its home stadium.
    $club = $clubs[4];
    expect(FixtureMatch::query()->where('home_club_id', $club->id)->count())->toBe(19)
        ->and(FixtureMatch::query()->where('away_club_id', $club->id)->count())->toBe(19)
        ->and(FixtureMatch::query()->where('home_club_id', $club->id)->where('stadium_id', $club->home_stadium_id)->count())->toBe(19);

    $this->actingAs($manager)
        ->get(PortalPages::url('sports_matches').'?edizione='.$edition->id.'&club='.$club->id)
        ->assertInertia(fn (Assert $page) => $page->has('matches', 38));
});

it('D-321: the confirmation writes one audit event with the counters, no event per match', function () {
    [$edition, $clubs] = importsEdition();
    $import = importUpload($edition, [
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02'),
        importRow(1, '03/09/2051', 'Prova 03', 'Prova 04'),
        importRow(1, '31/02/2052', 'Prova 03', 'Prova 01'),
    ]);

    $this->actingAs(User::factory()->admin()->create())->post(route('sports.imports.confirm', $import));

    $event = soleAuditEvent(AuditAction::CalendarImportConfirmed);
    expect($event->area)->toBe('sports')
        ->and($event->object_label)->toBe($edition->label())
        ->and($event->new_values)->toMatchArray(['file' => 'calendario-prova.xlsx', 'inserted' => 2, 'updated' => 0, 'unchanged' => 0, 'discarded' => 1])
        ->and(auditEventsOf(AuditAction::MatchCreated))->toHaveCount(0);
});

it('M02-CA2: an unknown team name is associated with a club as an alias; the next import recognizes it', function () {
    [$edition, $clubs] = importsEdition();
    $manager = User::factory()->manager()->create();
    $rows = [
        importRow(1, '03/09/2051', 'FC Prova Uno Vecchio Nome', 'Prova 02'),
        importRow(2, '10/09/2051', 'Prova 03', 'fc prova uno vecchio-nome'),
    ];

    $import = importUpload($edition, $rows, $manager);
    $preview = $import->previewData();

    expect($preview->unknownNames)->toBe([['name' => 'FC Prova Uno Vecchio Nome', 'rows' => 2]])
        ->and($preview->counts()['error'])->toBe(2)
        ->and(importPreviewLine($import, 2))->toMatchArray(['outcome' => 'error', 'unknown' => true])
        ->and(importPreviewLine($import, 2)['errors'])->toContain('Squadra «FC Prova Uno Vecchio Nome» da associare a un club');

    $this->actingAs($manager)
        ->post(route('sports.imports.resolve', $import), ['name' => 'FC Prova Uno Vecchio Nome', 'club_id' => $clubs[0]->id])
        ->assertSessionHas('toast.type', 'success');

    $alias = ClubAlias::query()->sole();
    expect($alias->club_id)->toBe($clubs[0]->id)
        ->and($alias->source)->toBe(AliasSource::Import)
        ->and(soleAuditEvent(AuditAction::ClubAliasCreated)->new_values)->toMatchArray(['source' => 'import']);

    $import->refresh();
    expect($import->previewData()->unknownNames)->toBe([])
        ->and($import->previewData()->counts()['new'])->toBe(2);

    // The next import of the same names needs no association.
    $import->update(['status' => CalendarImportStatus::Discarded, 'preview' => null]);
    $next = importUpload($edition, $rows, $manager);

    expect($next->previewData()->unknownNames)->toBe([])
        ->and($next->previewData()->counts()['new'])->toBe(2);
});

it('D-325: only the unknown names of the preview are associated; the alias rules still apply', function () {
    [$edition, $clubs] = importsEdition();
    $manager = User::factory()->manager()->create();
    $import = importUpload($edition, [importRow(1, '03/09/2051', 'Nome Ignoto', 'Prova 02')], $manager);

    $this->actingAs($manager)
        ->post(route('sports.imports.resolve', $import), ['name' => 'Prova 02', 'club_id' => $clubs[0]->id])
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', '«Prova 02» non è tra le squadre da associare di questo import');

    expect(ClubAlias::query()->count())->toBe(0);
});

it('M02-CA3: a re-import with a changed date updates the existing match without creating a new one', function () {
    [$edition] = importsEdition();
    $manager = User::factory()->manager()->create();
    $rows = [
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02'),
        importRow(1, '03/09/2051', 'Prova 03', 'Prova 04'),
    ];

    $first = importUpload($edition, $rows, $manager);
    $this->actingAs($manager)->post(route('sports.imports.confirm', $first));

    $rows[0] = importRow(1, '04/09/2051', 'Prova 01', 'Prova 02', '18:00');
    $second = importUpload($edition, $rows, $manager);

    expect($second->previewData()->counts())->toMatchArray(['new' => 0, 'update' => 1, 'unchanged' => 1, 'error' => 0])
        ->and(importPreviewLine($second, 2)['changes'])->toBe(['data 03/09/2051 → 04/09/2051', 'ora 20:45 → 18:00']);

    $this->actingAs($manager)->post(route('sports.imports.confirm', $second));

    $match = FixtureMatch::query()->whereDate('match_date', '2051-09-04')->sole();
    expect(FixtureMatch::query()->count())->toBe(2)
        ->and($match->kickoff())->toBe('18:00')
        ->and($match->last_import_id)->toBe($second->id)
        ->and($second->fresh()?->updated_count)->toBe(1)
        ->and($second->fresh()?->unchanged_count)->toBe(1);
});

it('M02 §8: the rows with errors are discarded with the reason', function () {
    [$edition, $clubs] = importsEdition();
    $stranger = Club::factory()->create(['short_name' => 'Estranea']);

    $import = importUpload($edition, [
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02'),           // 2: valid
        importRow(1, '31/02/2052', 'Prova 03', 'Prova 04'),           // 3: impossible date
        importRow(1, '', 'Prova 03', 'Prova 04'),                     // 4: date missing
        importRow(0, '03/09/2051', 'Prova 03', 'Prova 04'),           // 5: matchday
        importRow(2, '01/07/2052', 'Prova 03', 'Prova 04'),           // 6: outside the season
        importRow(2, '10/09/2051', 'Prova 03', 'Prova 03'),           // 7: same club
        importRow(2, '10/09/2051', 'Prova 01', 'Estranea'),           // 8: not enrolled
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02'),           // 9: repeated
        importRow(3, '17/09/2051', 'Prova 04', 'Prova 01', season: '2049-50'), // 10
        importRow(3, '17/09/2051', 'Prova 04', 'Prova 01', competition: 'Campionato Marziano'), // 11
        importRow(3, '17/09/2051', 'Prova 02', 'Prova 03', '25:99'),  // 12: time
    ]);

    $errors = fn (int $line): array => importPreviewLine($import, $line)['errors'];

    expect(importPreviewLine($import, 2)['outcome'])->toBe('new')
        ->and($errors(3))->toBe(['Data non valida'])
        ->and($errors(4))->toBe(['Data mancante'])
        ->and($errors(5))->toBe(['Giornata mancante o non valida: serve un numero intero da 1 a 99'])
        ->and($errors(6))->toBe(['La data è fuori dalla stagione 2051-52'])
        ->and($errors(7))->toBe(['La squadra di casa e la squadra ospite coincidono'])
        ->and($errors(8))->toBe([$stranger->name.' non è iscritto a '.$edition->label().': iscrivilo prima dalla pagina della stagione'])
        ->and($errors(9))->toBe(['Stessa partita della riga 2'])
        ->and($errors(10))->toBe(['Stagione «2049-50» diversa da quella scelta'])
        ->and($errors(11))->toBe(['Competizione «Campionato Marziano» diversa da quella scelta'])
        ->and($errors(12))->toBe(['Ora non valida: si scrive come 20:45'])
        ->and($import->previewData()->counts())->toMatchArray(['new' => 1, 'error' => 10]);

    $this->actingAs(User::factory()->manager()->create())->post(route('sports.imports.confirm', $import));

    expect(FixtureMatch::query()->count())->toBe(1)
        ->and($import->fresh()?->discarded_count)->toBe(10);
});

it('D-316: dates and times as Excel numbers, season and competition written in other forms', function () {
    [$edition] = importsEdition();

    // Serial number of Excel: days from 30 December 1899.
    $serial = (int) Carbon::create(1899, 12, 30)->diffInDays(Carbon::create(2051, 9, 3));
    $import = importUpload($edition, [
        ['2051/2052', $edition->competition->name, 1, $serial, 0.875, 'PROVA 01', 'prova  02', ''],
        ['Stagione 2051-52', $edition->competition->code, '2ª', '10-09-2051', '15.00', 'Prova 03', 'Prova 04', ''],
    ]);

    expect(importPreviewLine($import, 2))->toMatchArray(['outcome' => 'new', 'date' => '2051-09-03', 'time' => '21:00', 'matchday' => 1])
        ->and(importPreviewLine($import, 3))->toMatchArray(['outcome' => 'new', 'date' => '2051-09-10', 'time' => '15:00', 'matchday' => 2]);
});

it('D-319: an unknown stadium is a warning and the match gets the home stadium; a known one is used', function () {
    [$edition, $clubs] = importsEdition();
    $other = Stadium::factory()->create(['name' => 'Stadio Neutro Centrale']);

    $import = importUpload($edition, [
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02', stadium: 'Stadio che non esiste'),
        importRow(1, '03/09/2051', 'Prova 03', 'Prova 04', stadium: 'stadio neutro  centrale'),
    ]);

    expect(importPreviewLine($import, 2))->toMatchArray(['outcome' => 'new', 'stadium_id' => $clubs[0]->home_stadium_id])
        ->and(importPreviewLine($import, 2)['warnings'])->toBe(['Stadio «Stadio che non esiste» sconosciuto: si usa lo stadio abituale della squadra di casa'])
        ->and(importPreviewLine($import, 3))->toMatchArray(['stadium_id' => $other->id, 'warnings' => []]);
});

it('M02 §8: a team with two matches on the same day is a warning, not an error', function () {
    [$edition] = importsEdition();

    $import = importUpload($edition, [
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02'),
        importRow(2, '03/09/2051', 'Prova 03', 'Prova 01'),
    ]);

    expect(importPreviewLine($import, 3)['outcome'])->toBe('new')
        ->and(importPreviewLine($import, 3)['warnings'])->toContain('Società Prova 01 gioca già un\'altra partita il 03/09/2051 in questa competizione');
});

it('D-319, D-325: matches missing from the file stay; a cancelled match is updated and stays cancelled', function () {
    [$edition, $clubs] = importsEdition();
    $manager = User::factory()->manager()->create();
    $first = importUpload($edition, [
        importRow(1, '03/09/2051', 'Prova 01', 'Prova 02'),
        importRow(1, '03/09/2051', 'Prova 03', 'Prova 04'),
    ], $manager);
    $this->actingAs($manager)->post(route('sports.imports.confirm', $first));

    $cancelled = FixtureMatch::query()->where('home_club_id', $clubs[0]->id)->sole();
    $cancelled->update(['status' => MatchStatus::Cancelled]);

    $second = importUpload($edition, [importRow(1, '05/09/2051', 'Prova 01', 'Prova 02')], $manager);

    expect($second->previewData()->missingMatches)->toBe(['Prova 03 – Prova 04, 1ª giornata, 03/09/2051'])
        ->and(importPreviewLine($second, 2)['warnings'])->toBe(['Partita annullata: resta annullata']);

    $this->actingAs($manager)->post(route('sports.imports.confirm', $second));

    $cancelled->refresh();
    expect($cancelled->status)->toBe(MatchStatus::Cancelled)
        ->and($cancelled->match_date->toDateString())->toBe('2051-09-05')
        ->and(FixtureMatch::query()->where('home_club_id', $clubs[2]->id)->sole()->match_date->toDateString())->toBe('2051-09-03');
});

it('D-325: unknown headers ask for the columns; the mapping chosen by hand is saved and used', function () {
    [$edition] = importsEdition();
    $manager = User::factory()->manager()->create();
    $import = importUpload($edition, [
        [1, '03/09/2051', 'Prova 01', 'Prova 02'],
        [1, '03/09/2051', 'Prova 03', 'Prova 04'],
    ], $manager, ['N.', 'Quando', 'Padroni di casa', 'Visitatori']);

    expect($import->previewData()->missingColumns)->toBe(['matchday', 'date', 'home', 'away'])
        ->and($import->previewData()->rows)->toBe([]);

    $this->actingAs($manager)
        ->post(route('sports.imports.confirm', $import))
        ->assertSessionHas('toast.message', 'Non ci sono partite da importare: correggi le righe in errore o scegli le colonne');

    $this->actingAs($manager)
        ->put(route('sports.imports.columns', $import), ['columns' => ['matchday' => 0, 'date' => 1, 'home' => 1, 'away' => 3]])
        ->assertSessionHas('toast.message', 'Scegli una colonna diversa per ogni campo obbligatorio: giornata, data, squadra casa e squadra ospite');

    $this->actingAs($manager)
        ->put(route('sports.imports.columns', $import), ['columns' => ['matchday' => '0', 'date' => '1', 'time' => null, 'home' => '2', 'away' => '3']])
        ->assertSessionHas('toast.type', 'success');

    $import->refresh();
    expect($import->mapping())->toBe(['matchday' => 0, 'date' => 1, 'home' => 2, 'away' => 3])
        ->and($import->previewData()->counts()['new'])->toBe(2);
});

it('D-325: a team not enrolled is an error until the admin enrolls it and the preview is recalculated', function () {
    [$edition, $clubs] = importsEdition();
    $late = Club::factory()->create(['short_name' => 'Ritardataria']);
    $manager = User::factory()->manager()->create();
    $import = importUpload($edition, [importRow(1, '03/09/2051', 'Ritardataria', 'Prova 02')], $manager);

    expect(importPreviewLine($import, 2)['outcome'])->toBe('error');

    ClubEnrollment::query()->create(['competition_season_id' => $edition->id, 'club_id' => $late->id]);

    $this->actingAs($manager)
        ->post(route('sports.imports.refresh', $import))
        ->assertSessionHas('toast.message', 'Anteprima ricalcolata');

    expect(importPreviewLine($import, 2)['outcome'])->toBe('new');
});

it('D-317: a closed season refuses the upload and the confirmation', function () {
    [$edition] = importsEdition(closed: true);

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('sports.imports.store'), [
            'competition_season_id' => $edition->id,
            'file' => calendarFile([IMPORT_HEADER, importRow(1, '03/09/2051', 'Prova 01', 'Prova 02')]),
        ])
        ->assertSessionHas('toast.type', 'error')
        ->assertSessionHas('toast.message', 'La stagione 2051-52 è chiusa: le sue anagrafiche si consultano soltanto. Per modificarle riapri la stagione');

    expect(CalendarImport::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0);

    $import = CalendarImport::factory()->create(['competition_season_id' => $edition->id]);

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('sports.imports.confirm', $import))
        ->assertSessionHas('toast.type', 'error');
});

it('F-09: a workbook without matches is refused and nothing is kept', function () {
    [$edition] = importsEdition();

    $this->actingAs(User::factory()->manager()->create())
        ->post(route('sports.imports.store'), [
            'competition_season_id' => $edition->id,
            'file' => calendarFile([IMPORT_HEADER]),
        ])
        ->assertSessionHas('toast.message', 'Il primo foglio del file non ha partite: servono una riga di intestazione e una riga per ogni partita');

    expect(CalendarImport::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0);
});

it('FL-02: a discarded import changes nothing and is closed', function () {
    [$edition] = importsEdition();
    $manager = User::factory()->manager()->create();
    $import = importUpload($edition, [importRow(1, '03/09/2051', 'Prova 01', 'Prova 02')], $manager);

    $this->actingAs($manager)
        ->post(route('sports.imports.discard', $import))
        ->assertRedirect(PortalPages::url('sports_import'))
        ->assertSessionHas('toast.message', 'Import scartato: il calendario non è cambiato');

    $import->refresh();
    expect($import->status)->toBe(CalendarImportStatus::Discarded)
        ->and($import->preview)->toBeNull();

    $this->actingAs($manager)
        ->post(route('sports.imports.confirm', $import))
        ->assertSessionHas('toast.message', 'Questo import è già concluso: carica di nuovo il file per un nuovo import');

    expect(FixtureMatch::query()->count())->toBe(0);
});

it('the import page lists the imports of the season of the top bar', function () {
    [$edition] = importsEdition();
    $manager = User::factory()->manager()->create();
    $import = importUpload($edition, [importRow(1, '03/09/2051', 'Prova 01', 'Prova 02')], $manager);
    CalendarImport::factory()->create(); // another season

    $this->actingAs($manager)
        ->get(PortalPages::url('sports_import').'?edizione='.$edition->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sports/Imports/Index')
            ->where('selectedEdition', (string) $edition->id)
            ->has('imports', 1)
            ->where('imports.0.id', $import->id)
            ->where('imports.0.statusLabel', 'In anteprima')
            ->where('imports.0.uploadedBy', $manager->fullName()));
});

it('D-321: the calendar file is downloaded by admin and manager, not by the agent', function () {
    [$edition] = importsEdition();
    $import = importUpload($edition, [importRow(1, '03/09/2051', 'Prova 01', 'Prova 02')]);

    $this->actingAs(User::factory()->manager()->create())->get(route('files.download', $import->file_id))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route('files.download', $import->file_id))->assertOk();
    $this->actingAs(User::factory()->agent()->create())->get(route('files.download', $import->file_id))->assertForbidden();
});

it('calendar-imports:prune discards the previews untouched for more than 7 days', function () {
    $old = CalendarImport::factory()->create();
    $recent = CalendarImport::factory()->create();
    $confirmed = CalendarImport::factory()->create(['status' => CalendarImportStatus::Confirmed, 'confirmed_at' => now()->subMonth()]);
    CalendarImport::query()->whereKey([$old->id, $confirmed->id])->update(['updated_at' => now()->subDays(8)]);

    $this->artisan('calendar-imports:prune')
        ->expectsOutput('Import in anteprima scartati perché fermi da oltre 7 giorni: 1.')
        ->assertSuccessful();

    expect($old->fresh()?->status)->toBe(CalendarImportStatus::Discarded)
        ->and($old->fresh()?->preview)->toBeNull()
        ->and($recent->fresh()?->status)->toBe(CalendarImportStatus::Preview)
        ->and($confirmed->fresh()?->status)->toBe(CalendarImportStatus::Confirmed);
});
