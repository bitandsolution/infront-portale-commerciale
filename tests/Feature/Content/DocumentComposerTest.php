<?php

use App\Domain\Content\Data\ClubFacts;
use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Data\VariantFacts;
use App\Domain\Content\Services\DocumentComposer;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Models\Club;
use App\Models\CompetitionSeason;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Season;
use App\Models\Slide;
use App\Models\StoredFile;
use Database\Seeders\SystemDataSeeder;
use Illuminate\Support\Facades\Storage;

/*
| Composition of the document of a group of variants (M08 §4.2, CA1…CA4; M09 §3, §6; D-115, D-118,
| D-121, D-202, D-396, D-403…D-405; slice DM-02). The input is built by hand (CompositionInput), as the
| future simulator will do; one test builds it from a real group of proposals.
*/

beforeEach(function () {
    Storage::fake(StoredFile::DISK);
    $this->seed(SystemDataSeeder::class);
    $this->season = Season::factory()->create();
});

/**
 * A club row of a variant: tier code (null = no package), format code, hospitality, competition tier.
 */
function compRow(int $clubId, ?string $tier = null, ?string $format = null, bool $hospitality = false, ?int $competitionTier = 1): ClubFacts
{
    return new ClubFacts($clubId, $tier, $tier !== null && $tier !== 'LIBERA', $format, $hospitality, $competitionTier);
}

/**
 * @param  list<list<ClubFacts>>  $variants  club rows of variants A, B, C…
 */
function compBuild(Season $season, array $variants): Composition
{
    $letters = ['A', 'B', 'C', 'D', 'E'];

    return app(DocumentComposer::class)->compose(new CompositionInput(
        $season,
        array_map(fn (array $clubs, int $index): VariantFacts => new VariantFacts($letters[$index], $clubs), $variants, array_keys($variants)),
    ));
}

/**
 * Titles of the composed slides, in order.
 *
 * @return list<string>
 */
function compTitles(Composition $composition): array
{
    return array_map(fn (ComposedSlide $slide): string => $slide->slide->title, $composition->slides);
}

/**
 * Club slide for the club document.
 */
function compClubSlide(Season $season, Club $club, string $title, string $section = 'BENEFIT', int $order = 10): Slide
{
    return Slide::factory()->forSeason($season)->forClub($club)->document(SlideDocumentType::Club)
        ->section($section, $order)->create(['title' => $title]);
}

it('M08-CA1: a Lazio Top proposal includes the Top benefit slides and excludes Premium and Official', function () {
    $lazio = Club::factory()->infront()->create();
    $factory = fn (string $tier) => Slide::factory()->forSeason($this->season)->forClub($lazio)
        ->document(SlideDocumentType::Club)->section('BENEFIT')->withCondition(SlideConditionType::PartnershipTier, $tier);
    $factory('TOP')->create(['title' => 'Benefit Top']);
    $factory('PREMIUM')->create(['title' => 'Benefit Premium']);
    $factory('OFFICIAL')->create(['title' => 'Benefit Official']);

    $composition = compBuild($this->season, [[compRow($lazio->id, 'TOP')]]);

    expect($composition->documentType)->toBe(DocumentType::Club)
        ->and(compTitles($composition))->toBe(['Benefit Top']);
});

it('M08-CA2: a proposal without hospitality excludes the slides with "Hospitality presente = Sì"', function () {
    $club = Club::factory()->infront()->create();
    Slide::factory()->forSeason($this->season)->forClub($club)->document(SlideDocumentType::Club)
        ->withCondition(SlideConditionType::HasHospitality, 'true')->create(['title' => 'Ticketing & Hospitality']);
    Slide::factory()->forSeason($this->season)->forClub($club)->document(SlideDocumentType::Club)
        ->withCondition(SlideConditionType::HasHospitality, 'false')->create(['title' => 'Senza hospitality']);

    expect(compTitles(compBuild($this->season, [[compRow($club->id, 'TOP')]])))->toBe(['Senza hospitality'])
        ->and(compTitles(compBuild($this->season, [[compRow($club->id, 'TOP', hospitality: true)]])))->toBe(['Ticketing & Hospitality']);
});

it('M08-CA3: a slide with "Livello = Top o Premium" is included for both tiers, not for Official', function () {
    $club = Club::factory()->infront()->create();
    Slide::factory()->forSeason($this->season)->forClub($club)->document(SlideDocumentType::Club)
        ->withCondition(SlideConditionType::PartnershipTier, 'TOP', 'PREMIUM')->create(['title' => 'Top o Premium']);

    expect(compTitles(compBuild($this->season, [[compRow($club->id, 'TOP')]])))->toBe(['Top o Premium'])
        ->and(compTitles(compBuild($this->season, [[compRow($club->id, 'PREMIUM')]])))->toBe(['Top o Premium'])
        ->and(compTitles(compBuild($this->season, [[compRow($club->id, 'OFFICIAL')]])))->toBe([]);
});

it('M08-CA4: Bologna (Serie A) + Empoli (Serie B) include the Serie A and Serie B KPI slides; only Serie A excludes Serie B', function () {
    [$bologna, $empoli, $como] = Club::factory()->infront()->count(3)->create();
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Network)->section('CLUB', 10)
        ->withCondition(SlideConditionType::CompetitionTier, '1')->create(['title' => 'KPI Serie A']);
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Network)->section('CLUB', 20)
        ->withCondition(SlideConditionType::CompetitionTier, '2')->create(['title' => 'KPI Serie B']);

    $mixed = compBuild($this->season, [[compRow($bologna->id, 'TOP', competitionTier: 1), compRow($empoli->id, 'TOP', competitionTier: 2)]]);
    $serieA = compBuild($this->season, [[compRow($bologna->id, 'TOP', competitionTier: 1), compRow($como->id, 'TOP', competitionTier: 1)]]);

    expect($mixed->documentType)->toBe(DocumentType::Network)
        ->and(compTitles($mixed))->toBe(['KPI Serie A', 'KPI Serie B'])
        ->and(compTitles($serieA))->toBe(['KPI Serie A'])
        ->and($mixed->slides[1]->competitionTiers)->toBe([2]);
});

it('RB-01, D-202: different condition types must all hold; no conditions = always included', function () {
    $club = Club::factory()->infront()->create();
    Slide::factory()->forSeason($this->season)->forClub($club)->document(SlideDocumentType::Club)
        ->withCondition(SlideConditionType::PartnershipTier, 'TOP')->withCondition(SlideConditionType::LedFormat, 'DOMINATION')
        ->create(['title' => 'Top con Domination']);
    Slide::factory()->forSeason($this->season)->forClub($club)->document(SlideDocumentType::Club)->create(['title' => 'Sempre']);

    expect(compTitles(compBuild($this->season, [[compRow($club->id, 'TOP', 'DOMINATION')]])))->toBe(['Top con Domination', 'Sempre'])
        ->and(compTitles(compBuild($this->season, [[compRow($club->id, 'TOP', 'JUMBO')]])))->toBe(['Sempre']);
});

it('D-118: in the network document a condition holds when at least one club satisfies it', function () {
    [$lazio, $genoa] = Club::factory()->infront()->count(2)->create();
    foreach (['TOP', 'PREMIUM', 'OFFICIAL'] as $order => $tier) {
        Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Network)->section('BENEFIT', $order)
            ->withCondition(SlideConditionType::PartnershipTier, $tier)->create(['title' => 'Benefit '.$tier]);
    }

    $composition = compBuild($this->season, [[compRow($lazio->id, 'TOP'), compRow($genoa->id, 'PREMIUM')]]);

    expect(compTitles($composition))->toBe(['Benefit TOP', 'Benefit PREMIUM']);
});

it('D-404: with variants a condition holds when one club of one variant satisfies it', function () {
    $lazio = Club::factory()->infront()->create();
    Slide::factory()->forSeason($this->season)->forClub($lazio)->document(SlideDocumentType::Club)
        ->withCondition(SlideConditionType::HasLed, 'true')->create(['title' => 'LED bordocampo']);

    $composition = compBuild($this->season, [[compRow($lazio->id, 'TOP')], [compRow($lazio->id, 'TOP', 'FILA_1')]]);

    expect($composition->documentType)->toBe(DocumentType::Club)
        ->and(compTitles($composition))->toBe(['LED bordocampo']);
});

it('D-403: variants on two different single clubs make a network document with the Infront slides', function () {
    [$lazio, $roma] = Club::factory()->infront()->count(2)->create();
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Network)->create(['title' => 'Infront network']);
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Both)->create(['title' => 'Infront entrambi']);
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Club)->create(['title' => 'Infront solo club']);
    compClubSlide($this->season, $lazio, 'Lazio club');

    $composition = compBuild($this->season, [[compRow($lazio->id, 'TOP')], [compRow($roma->id, 'TOP')]]);

    expect($composition->documentType)->toBe(DocumentType::Network)
        ->and($composition->clubIds)->toBe([$lazio->id, $roma->id])
        ->and(compTitles($composition))->toBe(['Infront network', 'Infront entrambi']);
});

it('M09 §3: the club document uses only the active slides of its club, season and document type', function () {
    [$lazio, $roma] = Club::factory()->infront()->count(2)->create();
    compClubSlide($this->season, $lazio, 'Lazio club');
    Slide::factory()->forSeason($this->season)->forClub($lazio)->document(SlideDocumentType::Both)->create(['title' => 'Lazio entrambi']);
    Slide::factory()->forSeason($this->season)->forClub($lazio)->document(SlideDocumentType::Network)->create(['title' => 'Lazio network']);
    Slide::factory()->forSeason($this->season)->forClub($lazio)->document(SlideDocumentType::Club)->inactive()->create(['title' => 'Lazio disattivata']);
    Slide::factory()->forClub($lazio)->document(SlideDocumentType::Club)->create(['title' => 'Lazio altra stagione']);
    compClubSlide($this->season, $roma, 'Roma club');
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Both)->create(['title' => 'Infront']);

    $composition = compBuild($this->season, [[compRow($lazio->id, 'TOP')]]);

    expect(compTitles($composition))->toBe(['Lazio entrambi', 'Lazio club']);
});

it('RB-03: the order is section, then order in the section, then the oldest slide', function () {
    $club = Club::factory()->infront()->create();
    compClubSlide($this->season, $club, 'Chiusura', 'CLOSING', 10);
    compClubSlide($this->season, $club, 'Benefit 20', 'BENEFIT', 20);
    compClubSlide($this->season, $club, 'Benefit 10 prima', 'BENEFIT', 10);
    compClubSlide($this->season, $club, 'Benefit 10 dopo', 'BENEFIT', 10);
    compClubSlide($this->season, $club, 'Intro', 'INTRO', 50);

    expect(compTitles(compBuild($this->season, [[compRow($club->id, 'TOP')]])))
        ->toBe(['Intro', 'Benefit 10 prima', 'Benefit 10 dopo', 'Benefit 20', 'Chiusura']);
});

it('D-396, D-397, D-405: one recap page per variant, cover and recap mandatory, closing in the summary', function () {
    [$lazio, $roma] = Club::factory()->infront()->count(2)->create();
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Both)->layout(DocumentLayout::Cover)->section('INTRO', 1)->create(['title' => 'Copertina']);
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Network)->layout(DocumentLayout::NetworkRecap)->section('RECAP')->create(['title' => 'Recap']);
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Both)->section('BENEFIT')->create(['title' => 'Benefit']);
    Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Both)->section('CLOSING')->create(['title' => 'Grazie']);

    $composition = compBuild($this->season, [
        [compRow($lazio->id, 'TOP'), compRow($roma->id, 'TOP')],
        [compRow($lazio->id, 'PREMIUM')],
        [compRow($roma->id, 'OFFICIAL')],
    ]);
    [$cover, $benefit, $recap, $closing] = $composition->slides;

    expect(compTitles($composition))->toBe(['Copertina', 'Benefit', 'Recap', 'Grazie'])
        ->and(array_map(fn ($instance) => $instance->variantLetter, $recap->instances))->toBe(['A', 'B', 'C'])
        ->and([$cover->mandatory, $benefit->mandatory, $recap->mandatory, $closing->mandatory])->toBe([true, false, true, false])
        ->and([$cover->inSummary, $benefit->inSummary, $recap->inSummary, $closing->inSummary])->toBe([true, false, true, true])
        ->and($composition->pageCount())->toBe(6)
        ->and($composition->warnings)->toBe([]);
});

it('D-405: the LED simulation has one page per club with the LED, and none without LED', function () {
    [$lazio, $roma, $bologna] = Club::factory()->infront()->count(3)->create();
    $slide = Slide::factory()->forSeason($this->season)->document(SlideDocumentType::Both)->layout(DocumentLayout::LedMockup)
        ->section('BRAND_EXPOSURE')->create(['title' => 'Simulazione LED']);

    $withLed = compBuild($this->season, [
        [compRow($lazio->id, 'TOP', 'FILA_1'), compRow($roma->id, 'TOP')],
        [compRow($bologna->id, null, 'JUMBO'), compRow($lazio->id, null, 'FILA_2')],
    ]);
    $withoutLed = compBuild($this->season, [[compRow($lazio->id, 'TOP'), compRow($roma->id, 'PREMIUM')]]);

    expect($slide->image_file_id)->toBeNull()
        ->and(array_map(fn ($instance) => $instance->clubId, $withLed->slides[0]->instances))->toBe([$lazio->id, $bologna->id])
        ->and($withLed->slides[0]->inSummary)->toBeFalse()
        ->and(compTitles($withoutLed))->toBe([]);
});

it('D-405: missing cover, recap or closing are warnings, the composition goes on', function () {
    $club = Club::factory()->infront()->create();
    compClubSlide($this->season, $club, 'Benefit');
    Slide::factory()->forSeason($this->season)->forClub($club)->document(SlideDocumentType::Club)->layout(DocumentLayout::ClubRecap)
        ->section('RECAP')->create(['title' => 'Recap club']);

    $composition = compBuild($this->season, [[compRow($club->id, 'TOP')]]);

    expect(compTitles($composition))->toBe(['Benefit', 'Recap club'])
        ->and($composition->warnings)->toBe([
            'Manca la copertina attiva per il documento club',
            'Manca una slide attiva nella sezione Chiusura per il documento club',
        ]);
});

it('a group without clubs has no document type and no slides', function () {
    Slide::factory()->forSeason($this->season)->create();

    $composition = compBuild($this->season, [[]]);

    expect($composition->documentType)->toBeNull()
        ->and($composition->slides)->toBe([])
        ->and($composition->warnings)->toBe(['Il gruppo non ha club: nessun documento da comporre']);
});

it('D-396, D-404: the input of a group reads the working versions of its variants', function () {
    $competition = CompetitionSeason::factory()->for($this->season)->create();
    $competition->competition->update(['tier' => 2]);
    $club = Club::factory()->infront()->create();
    $variantA = Proposal::factory()->withWorkingVersion()->create(['season_id' => $this->season->id]);
    $variantB = Proposal::factory()->withWorkingVersion()->create([
        'season_id' => $this->season->id,
        'agent_id' => $variantA->agent_id,
        'client_id' => $variantA->client_id,
        'proposal_group_id' => $variantA->proposal_group_id,
        'variant_letter' => 'B',
    ]);
    $variantA->group?->update(['season_id' => $this->season->id]);
    $rowA = ProposalVersionClub::factory()->create([
        'proposal_version_id' => ProposalVersion::query()->where('proposal_id', $variantA->id)->value('id'),
        'club_id' => $club->id,
        'competition_season_id' => $competition->id,
        'partnership_tier_id' => PartnershipTier::query()->where('code', 'LIBERA')->value('id'),
    ]);
    $rowA->seats()->create(['hospitality_variant_id' => HospitalityVariant::query()->where('code', 'PREMIUM')->value('id'), 'seats' => 2]);
    ProposalVersionClub::factory()->led(LedFormat::query()->where('code', 'DOMINATION')->firstOrFail(), 60)->create([
        'proposal_version_id' => ProposalVersion::query()->where('proposal_id', $variantB->id)->value('id'),
        'club_id' => $club->id,
        'competition_season_id' => $competition->id,
    ]);

    $input = CompositionInput::fromGroup($variantA->group()->firstOrFail());
    [$a, $b] = $input->variants;

    expect($input->season?->id)->toBe($this->season->id)
        ->and([$a->letter, $b->letter])->toBe(['A', 'B'])
        ->and($a->clubs[0])->toEqual(new ClubFacts($club->id, 'LIBERA', false, null, true, 2))
        ->and($b->clubs[0])->toEqual(new ClubFacts($club->id, null, false, 'DOMINATION', false, 2))
        ->and(app(DocumentComposer::class)->compose($input)->documentType)->toBe(DocumentType::Club);
});
