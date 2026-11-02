<?php

use App\Enums\AliasSource;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\ExternalReference;
use Database\Factories\DisciplineFactory;

/*
| Clubs of the old system (D-229, D-318, D-329; slice F1-06): command sports:import-legacy-clubs on
| a fictitious CSV with the columns of idoc__teams_list (no data of the old system in the tests, §7).
*/

beforeEach(fn () => DisciplineFactory::football());

/**
 * A CSV with the header of the documented query and the given rows.
 *
 * @param  list<list<string|int>>  $rows  id_team, team_name, alias, main_color, team_top, deleted, team_category
 */
function legacyCsv(array $rows, string $separator = ';'): string
{
    $path = tempnam(sys_get_temp_dir(), 'idoc').'.csv';
    $handle = fopen($path, 'w');
    assert($handle !== false);
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, ['id_team', 'team_name', 'alias', 'main_color', 'team_top', 'deleted', 'team_category'], $separator, '"', '');

    foreach ($rows as $row) {
        fputcsv($handle, $row, $separator, '"', '');
    }

    fclose($handle);

    return $path;
}

/**
 * @return list<list<string|int>>
 */
function legacyRows(): array
{
    return [
        [1, 'SOCIETA ALFA', 'Alfa', '200,16,46', 0, 0, 1],
        [2, 'Beta Calcio', '', '0,0,0', 0, 1, 1],
        [3, 'Società Alfa', 'Alfa Vecchia', '0,0,0', 0, 1, 3],
        [4, 'xxx__GAMMA__xxx', 'Gamma', '0,0,0', 0, 1, 2],
        [5, 'US Gamma', 'Gamma', '10,20,30', 0, 0, 2],
        [6, 'S.S. Lazio', 'Lazio', '0,0,0', 0, 1, 1],
        [7, 'Delta', 'Delta', '0,0,0', 1, 1, 1],
        [8, 'Citt&agrave; Nuova', '', '300,0,0', 0, 1, 4],
    ];
}

it('D-318, D-329: creates the clubs with code, short name, colours, aliases and idoc references', function () {
    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows()), '--merge' => ['4,5']])
        ->expectsOutputToContain('Club creati: 6')
        ->expectsOutputToContain('Alias aggiunti: 2')
        ->expectsOutputToContain('Riferimenti idoc aggiunti: 8')
        ->assertSuccessful();

    $alfa = Club::query()->where('code', 'ALFA')->sole();
    expect($alfa->name)->toBe('SOCIETA ALFA')
        ->and($alfa->short_name)->toBe('Alfa')
        ->and($alfa->primary_color)->toBe('#C8102E')
        ->and($alfa->is_infront)->toBeTrue()
        ->and($alfa->is_active)->toBeTrue()
        ->and(ClubAlias::query()->where('club_id', $alfa->id)->pluck('alias')->all())->toBe(['Alfa Vecchia'])
        ->and(ExternalReference::query()->where('entity_id', $alfa->id)->orderBy('external_id')->pluck('external_id')->all())->toBe(['1', '3']);

    $beta = Club::query()->where('code', 'BETA_CALCIO')->sole();
    expect($beta->short_name)->toBe('Beta Calcio')
        ->and($beta->primary_color)->toBeNull()
        ->and($beta->is_infront)->toBeFalse()
        ->and($beta->is_active)->toBeTrue();

    // Joined with --merge: the main row is the Infront one (deleted = 0).
    $gamma = Club::query()->where('code', 'GAMMA')->sole();
    expect($gamma->name)->toBe('US Gamma')
        ->and($gamma->primary_color)->toBe('#0A141E')
        ->and($gamma->is_infront)->toBeTrue()
        ->and(ClubAlias::query()->where('club_id', $gamma->id)->sole()->alias)->toBe('xxx__GAMMA__xxx')
        ->and(ClubAlias::query()->where('club_id', $gamma->id)->sole()->source)->toBe(AliasSource::Legacy);

    // HTML entities decoded; invalid colour ignored.
    $citta = Club::query()->where('code', 'CITTA_NUOVA')->sole();
    expect($citta->name)->toBe('Città Nuova')
        ->and($citta->primary_color)->toBeNull();

    // D-257: the flag follows the list, not team_top.
    expect(Club::query()->where('code', 'LAZIO')->sole()->is_top_match_opponent)->toBeTrue()
        ->and(Club::query()->where('code', 'DELTA')->sole()->is_top_match_opponent)->toBeFalse();
});

it('D-318, D-329: reports the anomalies and writes one audit event by the system', function () {
    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows()), '--merge' => ['4,5']])
        ->expectsOutputToContain('id 3 «Società Alfa»: categoria 3 inesistente nel vecchio sistema')
        ->expectsOutputToContain('id 8: colore «300,0,0» non valido, ignorato')
        ->expectsOutputToContain('LAZIO: flag top match aggiunto per D-257')
        ->expectsOutputToContain('DELTA: nel vecchio sistema aveva il flag top match, che D-257 non prevede')
        ->expectsOutputToContain('Club INTER dell\'elenco top match (D-257) non presente nel portale')
        ->assertSuccessful();

    $event = AuditEvent::query()->where('action', AuditAction::ClubLegacyImported->value)->sole();
    expect($event->user_id)->toBeNull()
        ->and($event->user_name)->toBe(AuditEvent::SYSTEM)
        ->and($event->area)->toBe('sports')
        ->and($event->new_values)->toMatchArray(['created' => 6, 'existing' => 0, 'aliases' => 2, 'references' => 8]);
});

it('D-329: a repeated code stops the import before any write', function () {
    $path = legacyCsv([
        [1, 'Prima Società', 'Omega', '0,0,0', 0, 0, 1],
        [2, 'Seconda Società', 'Omega', '0,0,0', 0, 1, 1],
        [3, 'Terza', '9 Stelle', '0,0,0', 0, 1, 1],
    ]);

    $this->artisan('sports:import-legacy-clubs', ['file' => $path])
        ->expectsOutputToContain('id 2 «Seconda Società» darebbe il codice «OMEGA», già usato; id 3 «Terza» darebbe il codice non valido «9_STELLE»')
        ->assertFailed();

    expect(Club::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', AuditAction::ClubLegacyImported->value)->count())->toBe(0);

    // Joined with --merge, the same rows go through (the invalid code is fixed in the file).
    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv([
        [1, 'Prima Società', 'Omega', '0,0,0', 0, 0, 1],
        [2, 'Seconda Società', 'Omega', '0,0,0', 0, 1, 1],
    ]), '--merge' => ['1,2']])->assertSuccessful();

    expect(Club::query()->where('code', 'OMEGA')->sole()->name)->toBe('Prima Società');
});

it('D-329: a club already in the portal is not changed; the command can run again', function () {
    $manual = Club::factory()->create(['code' => 'ALFA_MANO', 'name' => 'Società Alfa', 'short_name' => 'Alfa a mano']);

    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows()), '--merge' => ['4,5']])
        ->expectsOutputToContain('Club già presenti, non modificati: 1')
        ->expectsOutputToContain('= ALFA_MANO – Società Alfa')
        ->assertSuccessful();

    $manual->refresh();
    expect($manual->short_name)->toBe('Alfa a mano')
        ->and($manual->primary_color)->toBeNull()
        ->and(ExternalReference::query()->where('entity_id', $manual->id)->count())->toBe(2)
        ->and(Club::query()->where('code', 'ALFA')->exists())->toBeFalse();

    $clubs = Club::query()->count();

    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows(), ','), '--merge' => ['4,5']])
        ->expectsOutputToContain('Club creati: 0')
        ->expectsOutputToContain('Alias aggiunti: 0')
        ->expectsOutputToContain('Riferimenti idoc aggiunti: 0')
        ->assertSuccessful();

    expect(Club::query()->count())->toBe($clubs);
});

it('D-318: an alias that already identifies another club is reported, not added', function () {
    Club::factory()->create(['code' => 'ZETA', 'name' => 'Zeta Vecchia', 'short_name' => 'Zeta']);

    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv([
        [1, 'Nuova Zeta', 'Zeta Nuova', '0,0,0', 0, 0, 1],
        [2, 'Nuova Zeta', 'Zeta', '0,0,0', 0, 1, 1],
    ])])
        ->expectsOutputToContain('Alias «Zeta» di ZETA_NUOVA non aggiunto: indica già ZETA')
        ->assertSuccessful();
});

it('--dry-run shows the report and saves nothing', function () {
    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows()), '--merge' => ['4,5'], '--dry-run' => true])
        ->expectsOutputToContain('Prova senza salvare: nessuna modifica scritta.')
        ->expectsOutputToContain('Club creati: 6')
        ->assertSuccessful();

    expect(Club::query()->count())->toBe(0)
        ->and(ClubAlias::query()->count())->toBe(0)
        ->and(ExternalReference::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', AuditAction::ClubLegacyImported->value)->count())->toBe(0);
});

it('refuses a file without the columns, an unknown id and a malformed --merge', function () {
    $path = tempnam(sys_get_temp_dir(), 'idoc');
    file_put_contents($path, "id;nome\n1;Alfa\n");

    $this->artisan('sports:import-legacy-clubs', ['file' => $path])
        ->expectsOutputToContain('Il file deve avere la riga di intestazione con le colonne: id_team, team_name, alias, main_color, team_top, deleted, team_category')
        ->assertFailed();

    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows()), '--merge' => ['4,99']])
        ->expectsOutputToContain('L\'id 99 indicato in --merge non è nel file')
        ->assertFailed();

    $this->artisan('sports:import-legacy-clubs', ['file' => legacyCsv(legacyRows()), '--merge' => ['4']])
        ->expectsOutputToContain('Formato di --merge non valido: «4» (esempio: --merge=21,35)')
        ->assertFailed();

    expect(Club::query()->count())->toBe(0);
});
