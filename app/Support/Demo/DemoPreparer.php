<?php

namespace App\Support\Demo;

use App\Domain\Catalog\Actions\CreatePriceList;
use App\Domain\Catalog\Actions\PublishPriceList;
use App\Domain\Catalog\Actions\SetPrice;
use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Data\ClientData;
use App\Domain\Content\Actions\CreateSlide;
use App\Domain\Content\Actions\SaveLedMockupSetting;
use App\Domain\Content\Data\SlideData;
use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Documents\Services\DocumentFonts;
use App\Domain\Inventory\Actions\SetClubCapacities;
use App\Domain\Inventory\Data\CapacityInput;
use App\Domain\Proposals\Actions\AddVariant;
use App\Domain\Proposals\Actions\ApproveGroup;
use App\Domain\Proposals\Actions\AttachCustomSlideImage;
use App\Domain\Proposals\Actions\CreateCustomSlide;
use App\Domain\Proposals\Actions\CreateDraft;
use App\Domain\Proposals\Actions\SaveCustomSlide;
use App\Domain\Proposals\Actions\SaveDraft;
use App\Domain\Proposals\Actions\SubmitGroup;
use App\Domain\Proposals\Data\CustomSlideInput;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Sports\Actions\AddCompetitionToSeason;
use App\Domain\Sports\Actions\ChangeClubLogo;
use App\Domain\Sports\Actions\CreateClub;
use App\Domain\Sports\Actions\CreateSeason;
use App\Domain\Sports\Actions\CreateStadium;
use App\Domain\Sports\Actions\EnrollClubs;
use App\Domain\Sports\Actions\SaveMatch;
use App\Domain\Sports\Data\ClubData;
use App\Domain\Sports\Data\MatchData;
use App\Domain\Sports\Data\SeasonData;
use App\Domain\Sports\Data\StadiumData;
use App\Enums\CustomSlideLayout;
use App\Enums\DiscountType;
use App\Enums\DocumentLayout;
use App\Enums\InventoryResource;
use App\Enums\LedPhotoKind;
use App\Enums\MatchdayStage;
use App\Enums\PriceKind;
use App\Enums\Role;
use App\Enums\SeasonStatus;
use App\Enums\SlideConditionType;
use App\Enums\SlideDocumentType;
use App\Enums\SlideScope;
use App\Models\Client;
use App\Models\Club;
use App\Models\CommoditySector;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\HospitalityVariant;
use App\Models\InventoryPool;
use App\Models\LedFormat;
use App\Models\LedMockupSetting;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\Season;
use App\Models\SlideSection;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Text;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Prepares the data of the demo of 4/12/2026 on a database just created with
 * `migrate:fresh --seed` (D-400, D-438…D-442; DM-08). Every write goes through the Actions of the
 * portal, with their checks, calculations and audit events; the author is the admin created with
 * `users:create-admin`, the proposals belong to agente1 of the development seeder.
 *
 * 1. Season 2027-28 (open), Serie A with 20 clubs (the 5 of the demo are clubs of Infront) and a
 *    fictitious calendar of 380 matches; Serie B with the standard number, no clubs (D-441).
 * 2. Published price list and managed capacities of the 5 clubs.
 * 3. Fictitious clients with drawn logos (one opaque, the others transparent: LED strip, D-425).
 * 4. Slide library for the network and for every club: cover, recap, LED simulation, background of
 *    the composed slide, static slides with conditions, closing (D-405, D-428, D-438).
 * 5. LED simulations: generic Infront and Udinese, from the photos of the demo or a drawn field.
 * 6. Proposals (D-440): A/B/C sent for approval; dates "from the start / from January" in draft;
 *    Udinese approved with a 10% discount and a composed slide (the PDF needs the queue worker).
 */
final class DemoPreparer
{
    /** Height of the logo in % of the LED and spacing, as the defaults of the editor (DM-06). */
    private const LOGO_HEIGHT = LedMockupSetting::DEFAULT_LOGO_HEIGHT_PERCENT;

    private const SPACING = LedMockupSetting::DEFAULT_SPACING;

    /** @var list<string> */
    private array $report = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    private User $admin;

    private User $agent;

    private Season $season;

    /** @var array<string, Club> */
    private array $clubs = [];

    /** @var array<string, Client> */
    private array $clients = [];

    private DemoArtwork $artwork;

    public function __construct(private readonly DemoAssets $assets) {}

    /**
     * Refusals before writing anything: the data are prepared once, on an empty portal.
     */
    public function check(): ?string
    {
        if (Season::query()->exists() || Club::query()->exists()) {
            return Text::get('demo.console.not_empty');
        }

        if ($this->firstAdmin() === null) {
            return Text::get('demo.console.no_admin');
        }

        foreach ([DemoData::AGENT_EMAIL, DemoData::SECOND_AGENT_EMAIL] as $email) {
            if (! User::query()->where('email', $email)->where('role', Role::Agent->value)->where('is_active', true)->exists()) {
                return Text::get('demo.console.no_agents', ['email' => $email]);
            }
        }

        if ($this->assets->root() !== null && ! $this->assets->exists()) {
            return Text::get('demo.console.assets_missing', ['path' => (string) $this->assets->root()]);
        }

        return null;
    }

    /**
     * @return list<string> lines of the report
     */
    public function prepare(?callable $progress = null): array
    {
        $this->admin = $this->firstAdmin() ?? throw new RuntimeException('No active admin.');
        $this->agent = User::query()->where('email', DemoData::AGENT_EMAIL)->firstOrFail();
        $this->artwork = new DemoArtwork($this->fonts());

        $step = function (string $step) use ($progress): void {
            if ($progress !== null) {
                $progress(Text::get('demo.console.steps.'.$step));
            }
        };

        try {
            $step('sports');
            $this->sports();
            $step('calendar');
            $this->calendar();
            $step('catalog');
            $this->catalog();
            $step('clients');
            $this->clients();
            $step('slides');
            $this->slides();
            $step('led');
            $this->ledMockups();
            $step('proposals');
            $this->proposals();
        } finally {
            foreach ($this->temporaryFiles as $file) {
                @unlink($file);
            }
        }

        foreach ($this->assets->rejected() as $file) {
            $this->report[] = Text::get('demo.console.rejected', ['file' => $file]);
        }

        return $this->report;
    }

    private function sports(): void
    {
        $this->season = app(CreateSeason::class)->handle(new SeasonData(
            DemoData::SEASON_CODE,
            DemoData::SEASON_NAME,
            Carbon::parse(DemoData::SEASON_START),
            Carbon::parse(DemoData::SEASON_END),
            SeasonStatus::Open,
        ), $this->admin);

        $stadiums = [];

        foreach (DemoData::STADIUMS as [$key, $name, $city]) {
            $stadiums[$key] = app(CreateStadium::class)->handle(new StadiumData($name, $city, null, true), $this->admin);
        }

        foreach (DemoData::CLUBS as [$code, $name, $shortName, $primary, $secondary, $stadium, $top]) {
            $club = app(CreateClub::class)->handle(new ClubData(
                $code,
                $name,
                $shortName,
                $stadiums[$stadium]->id,
                $primary,
                $secondary,
                in_array($code, DemoData::DEMO_CLUBS, true),
                $top,
                true,
            ), $this->admin);

            if (in_array($code, DemoData::DEMO_CLUBS, true)) {
                $real = $this->assets->clubLogo($code);
                $logo = $real ?? $this->artwork->clubLogo(mb_strtoupper(mb_substr($shortName, 0, 3)), DemoArtwork::rgb($primary));
                $club = app(ChangeClubLogo::class)->handle($club, $this->upload($logo, strtolower($code).'.png'), $this->admin);
                $this->report[] = Text::get($real !== null ? 'demo.console.logo_real' : 'demo.console.logo_drawn', ['club' => $shortName]);
            }

            $this->clubs[$code] = $club;
        }

        $serieA = app(AddCompetitionToSeason::class)->handle($this->season, $this->competition(DemoData::SERIE_A), DemoData::STANDARD_HOME_MATCHES, $this->admin);
        app(AddCompetitionToSeason::class)->handle($this->season, $this->competition(DemoData::SERIE_B), DemoData::STANDARD_HOME_MATCHES, $this->admin);
        app(EnrollClubs::class)->handle($serieA, array_values(array_map(fn (Club $club): int => $club->id, $this->clubs)), $this->admin);

        $this->report[] = Text::get('demo.console.sports', ['clubs' => (string) count($this->clubs)]);
    }

    /**
     * Double round robin (circle method) on the clubs in order of code, one matchday a week from
     * Sunday 22/08/2027; never more than two home or away matches in a row (script of the milestone A,
     * docs/dev/collaudo-milestone-a.md §8.1), 19 home matches per club.
     */
    private function calendar(): void
    {
        $edition = CompetitionSeason::query()
            ->where('season_id', $this->season->id)
            ->where('competition_id', $this->competition(DemoData::SERIE_A)->id)
            ->firstOrFail();

        $teams = array_keys($this->clubs);
        sort($teams);

        $first = Carbon::parse(DemoData::FIRST_MATCHDAY);
        $count = count($teams);
        $rounds = $count - 1;
        $matches = [];

        for ($round = 0; $round < $rounds; $round++) {
            for ($index = 0; $index < intdiv($count, 2); $index++) {
                $a = $teams[$index];
                $b = $teams[$count - 1 - $index];

                if ($index === 0) {
                    [$home, $away] = $round % 2 === 0 ? [$a, $b] : [$b, $a];
                } else {
                    [$home, $away] = $index % 2 === 1 ? [$a, $b] : [$b, $a];
                }

                $matches[] = [$round + 1, $home, $away];
                $matches[] = [$round + 1 + $rounds, $away, $home];
            }

            $teams = [$teams[0], $teams[$count - 1], ...array_slice($teams, 1, $count - 2)];
        }

        usort($matches, fn (array $x, array $y): int => $x[0] <=> $y[0]);

        foreach ($matches as [$matchday, $home, $away]) {
            app(SaveMatch::class)->handle(null, new MatchData(
                $edition->id,
                MatchdayStage::Regular,
                $matchday,
                $first->copy()->addWeeks($matchday - 1),
                DemoData::KICKOFF,
                $this->clubs[$home]->id,
                $this->clubs[$away]->id,
                null,
            ), $this->admin);
        }

        $this->report[] = Text::get('demo.console.calendar', ['matches' => (string) count($matches)]);
    }

    private function catalog(): void
    {
        $priceList = app(CreatePriceList::class)->handle($this->season, $this->admin);
        $setPrice = app(SetPrice::class);

        foreach (DemoData::PRICE_LEVELS as $code => $level) {
            $club = $this->clubs[$code];

            foreach (DemoData::LED_PRICES as $format => $price) {
                $setPrice->handle($priceList, PriceKind::Led, $club, $this->format($format), self::amount($price, $level), $this->admin);
            }

            foreach (DemoData::PARTNERSHIP_PRICES as $tier => $price) {
                $setPrice->handle($priceList, PriceKind::Partnership, $club, $this->tier($tier), self::amount($price, $level), $this->admin);
            }

            foreach (DemoData::SEAT_PRICES as $variant => $price) {
                $setPrice->handle($priceList, PriceKind::Hospitality, $club, $this->seatVariant($variant), self::amount($price, $level), $this->admin);
            }

            $inputs = [];

            foreach (DemoData::POOL_SECONDS as $pool => $seconds) {
                $inputs[] = new CapacityInput(InventoryResource::Pool, InventoryPool::query()->where('code', $pool)->firstOrFail()->id, true, $seconds);
            }

            foreach (DemoData::TIER_CONTRACTS as $tier => $contracts) {
                $inputs[] = new CapacityInput(InventoryResource::Partnership, $this->tier($tier)->id, true, $contracts);
            }

            foreach (DemoData::SEATS as $variant => $seats) {
                $inputs[] = new CapacityInput(InventoryResource::Hospitality, $this->seatVariant($variant)->id, true, $seats);
            }

            app(SetClubCapacities::class)->handle($this->season, $club, $inputs, true, $this->admin);
        }

        app(PublishPriceList::class)->handle($priceList, $this->admin);
        $this->report[] = Text::get('demo.console.catalog', ['clubs' => (string) count(DemoData::PRICE_LEVELS)]);
    }

    private function clients(): void
    {
        foreach (DemoData::CLIENTS as [$key, $name, $sector, $owner, $opaque, $background, $text]) {
            $logo = $opaque
                ? $this->artwork->opaqueLogo($key, $background, $text)
                : $this->artwork->transparentLogo($key, $background);

            $this->clients[$key] = app(CreateClient::class)->handle(
                new ClientData(
                    $name,
                    null,
                    strtolower($key).'@example.test',
                    CommoditySector::query()->where('code', $sector)->firstOrFail()->id,
                    null,
                    Text::get('demo.contact'),
                ),
                $this->upload($logo, strtolower($key).'.png'),
                User::query()->where('email', $owner)->firstOrFail(),
                $this->admin,
            );
        }

        $this->report[] = Text::get('demo.console.clients', ['clients' => (string) count($this->clients)]);
    }

    /**
     * The slides of the network (scope Infront) and of every club of the demo (scope club). Each one
     * is drawn, or taken from slide/<PREFIX>_<KEY> of the folder of the demo (D-438).
     */
    private function slides(): void
    {
        $replaced = 0;
        $created = 0;
        $prefixes = ['INFRONT' => null];

        foreach (DemoData::DEMO_CLUBS as $code) {
            $prefixes[$code] = $this->clubs[$code];
        }

        foreach ($prefixes as $prefix => $club) {
            foreach ($this->slideDefinitions($club) as [$key, $section, $order, $layout, $conditions, $drawn]) {
                $image = null;

                if ($layout?->needsImage() !== false) {
                    $real = $this->assets->slide($prefix, $key);
                    $replaced += $real !== null ? 1 : 0;
                    $image = $this->upload($real ?? $drawn(), strtolower($prefix.'_'.$key).'.png');
                }

                app(CreateSlide::class)->handle($this->season, new SlideData(
                    Text::get('demo.slides.'.($club !== null ? 'club' : 'network').'.'.strtolower($key), ['club' => $club !== null ? $club->short_name : '']),
                    $club !== null ? SlideScope::Club : SlideScope::Infront,
                    $club?->id,
                    $club !== null ? SlideDocumentType::Club : SlideDocumentType::Network,
                    SlideSection::query()->where('code', $section)->firstOrFail()->id,
                    $order,
                    $layout,
                    true,
                    $conditions,
                ), $image, $this->admin);
                $created++;
            }
        }

        $this->report[] = Text::get('demo.console.slides', ['slides' => (string) $created, 'replaced' => (string) $replaced]);
    }

    /**
     * Slides of the network ($club null) or of a club: key, section, order, layout, conditions and
     * the drawing.
     *
     * @return list<array{string, string, int, DocumentLayout|null, array<string, list<string>>, Closure(): string}>
     */
    private function slideDefinitions(?Club $club): array
    {
        $rgb = $club !== null ? DemoArtwork::rgb($club->primary_color) : DemoArtwork::navy();
        $accent = $club !== null ? $rgb : DemoArtwork::RED;
        $type = $club !== null ? 'club' : 'network';
        $name = $club !== null ? $club->short_name : 'Infront';
        $text = fn (string $key): string => Text::get('demo.drawn.'.$key, ['club' => $name]);
        $slide = fn (string $key): Closure => fn (): string => $this->artwork->slide($text($key.'_title'), $text($key.'_text'), $rgb);

        $definitions = [
            ['COPERTINA', 'INTRO', 10, DocumentLayout::Cover, [], fn (): string => $this->artwork->cover($type, $club !== null ? $club->name : $text('cover_title'), DemoData::SEASON_NAME, $rgb)],
        ];

        if ($club === null) {
            $definitions[] = ['INTRO', 'INTRO', 20, null, [], $slide('intro')];
            $definitions[] = ['NETWORK', 'CLUB', 10, null, [], $slide('network')];
            $definitions[] = ['TOP', 'PARTNERSHIP', 20, null, [SlideConditionType::PartnershipTier->value => ['TOP']], $slide('top')];
            $definitions[] = ['LED', 'BRAND_EXPOSURE', 10, null, [SlideConditionType::HasLed->value => ['true']], $slide('led')];
            $definitions[] = ['JUMBO', 'BRAND_EXPOSURE', 15, null, [SlideConditionType::LedFormat->value => ['DOMINATION', 'JUMBO']], $slide('jumbo')];
        } else {
            $definitions[] = ['CLUB', 'CLUB', 10, null, [], $slide('club')];
        }

        $definitions[] = ['PARTNERSHIP', 'PARTNERSHIP', 10, null, [SlideConditionType::HasPartnership->value => ['true']], $slide('partnership')];
        $definitions[] = ['HOSPITALITY', 'BENEFIT', 10, null, [SlideConditionType::HasHospitality->value => ['true']], $slide('hospitality')];
        $definitions[] = ['SIMULAZIONE', 'BRAND_EXPOSURE', 20, DocumentLayout::LedMockup, [], fn (): string => ''];
        $definitions[] = ['PERSONALIZZATA', 'BRAND_EXPOSURE', 90, DocumentLayout::CustomBlocks, [], fn (): string => $this->artwork->light($text('custom_title'), $accent)];
        $definitions[] = ['RECAP', 'RECAP', 10, $club !== null ? DocumentLayout::ClubRecap : DocumentLayout::NetworkRecap, [], fn (): string => $this->artwork->light($text('recap_title'), $accent, $club?->name)];
        $definitions[] = ['CHIUSURA', 'CLOSING', 10, null, [], $slide('closing')];

        return $definitions;
    }

    /**
     * Generic Infront simulations and those of the Udinese (D-398, D-427): the photos of the folder
     * of the demo, otherwise the drawn field for the generic ones only.
     */
    private function ledMockups(): void
    {
        foreach (DemoData::FIELD_PHOTOS as $owner => $photos) {
            $club = $owner === 'INFRONT' ? null : $this->clubs[$owner];
            [$upper, $lower] = [DemoData::FIELD_AREAS[$owner]['upper'], DemoData::FIELD_AREAS[$owner]['lower']];

            foreach ($photos as $kind => $file) {
                $path = $this->assets->fieldPhoto($file);

                if ($path === null && $club !== null) {
                    $this->report[] = Text::get('demo.console.photo_missing', ['file' => $file]);

                    continue;
                }

                $photoKind = LedPhotoKind::from($kind);
                $areas = match ($photoKind) {
                    LedPhotoKind::Row1 => [self::area($lower)],
                    LedPhotoKind::Row2 => [self::area($upper)],
                    LedPhotoKind::BothRows => [self::area($upper), self::area($lower)],
                };

                $contents = $path !== null ? (string) file_get_contents($path) : $this->artwork->fieldPhoto();
                $photo = $this->upload($contents, strtolower($file).($path !== null ? '.'.strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '.jpg'));
                app(SaveLedMockupSetting::class)->handle($club, $photoKind, $photo, $areas, self::LOGO_HEIGHT, self::SPACING, $this->admin);
                $this->report[] = Text::get($path !== null ? 'demo.console.photo_real' : 'demo.console.photo_drawn', ['file' => $file]);
            }
        }
    }

    /**
     * The three groups of D-440, all of agente1.
     */
    private function proposals(): void
    {
        // (a) A Lazio; B Lazio and Roma; C the five clubs: sent for approval.
        $a = $this->draft('AURORA', [
            $this->choice('LAZIO', 'TOP', 'FILA_1', 60),
        ]);
        $this->variant($a, [
            $this->choice('LAZIO', 'PREMIUM', 'FILA_1', 60),
            $this->choice('ROMA', 'PREMIUM', 'FILA_2', 60),
        ]);
        $c = $this->variant($a, [
            $this->choice('LAZIO', 'OFFICIAL', 'FILA_1', 30),
            $this->choice('ROMA', 'OFFICIAL', 'FILA_2', 30),
            $this->choice('BOLOGNA', 'OFFICIAL', 'JUMBO', 30),
            $this->choice('UDINESE', 'PREMIUM', 'DOMINATION', 30, ['PREMIUM' => 2]),
            $this->choice('COMO', 'OFFICIAL', 'FILA_1', 60),
        ]);
        app(SubmitGroup::class)->handle($this->group($a), $this->agent);
        $this->report[] = Text::get('demo.console.group_submitted', ['number' => (string) $a->number, 'last' => (string) $c->number]);

        // (b) The same configuration of the Roma from the start of the season and from January.
        $roma = [$this->choice('ROMA', 'PREMIUM', 'FILA_2', 60, ['PREMIUM' => 2])];
        $season = $this->draft('VELA', $roma);
        $january = $this->variant($season, $roma, DemoData::JANUARY_START);
        $this->report[] = Text::get('demo.console.group_dates', [
            'number' => (string) $season->number,
            'season' => $this->listTotal($season),
            'january_number' => (string) $january->number,
            'january' => $this->listTotal($january),
        ]);

        // (c) Udinese approved with a composed slide and 10% discount: the PDF is queued.
        $udinese = $this->draft('MONTEVERDE', [
            $this->choice('UDINESE', 'TOP', 'JUMBO', 60, ['PREMIUM' => 4]),
        ]);
        $this->customSlide($this->group($udinese));
        app(SubmitGroup::class)->handle($this->group($udinese), $this->agent);
        app(ApproveGroup::class)->handle($this->group($udinese), [
            $udinese->id => new DiscountInput(DiscountType::Percent, '10'),
        ], $this->admin);
        $this->report[] = Text::get('demo.console.group_approved', ['number' => (string) $udinese->number]);
    }

    /**
     * @param  list<DraftClubData>  $clubs
     */
    private function draft(string $client, array $clubs, ?string $startsOn = null): Proposal
    {
        $proposal = app(CreateDraft::class)->handle($this->agent, $this->clients[$client]);

        return $this->save($proposal, $clubs, $startsOn);
    }

    /**
     * @param  list<DraftClubData>  $clubs
     */
    private function variant(Proposal $proposal, array $clubs, ?string $startsOn = null): Proposal
    {
        $variant = app(AddVariant::class)->handle($proposal->refresh(), $this->agent)->proposal;

        return $this->save($variant, $clubs, $startsOn);
    }

    /**
     * @param  list<DraftClubData>  $clubs
     */
    private function save(Proposal $proposal, array $clubs, ?string $startsOn): Proposal
    {
        $proposal->refresh();

        app(SaveDraft::class)->handle($proposal, new DraftData(
            $proposal->client_id,
            $this->season->id,
            $startsOn ?? DemoData::SEASON_START,
            DemoData::SEASON_END,
            $clubs,
        ), $proposal->lock_version, $this->agent);

        return $proposal->refresh();
    }

    /**
     * @param  array<string, int>  $seats  variant code => seats
     */
    private function choice(string $club, ?string $tier, ?string $format, int $seconds, array $seats = []): DraftClubData
    {
        $byVariant = [];

        foreach ($seats as $variant => $count) {
            $byVariant[$this->seatVariant($variant)->id] = $count;
        }

        return new DraftClubData(
            $this->clubs[$club]->id,
            $tier !== null ? $this->tier($tier)->id : null,
            $format !== null ? $this->format($format)->id : null,
            $format !== null ? $seconds : 0,
            $byVariant,
        );
    }

    /**
     * The composed slide of the Udinese group (D-399): two columns with title, text, table and an
     * image block, at the end of Brand exposure, right before the recap.
     */
    private function customSlide(ProposalGroup $group): void
    {
        $slide = app(CreateCustomSlide::class)->handle($group, $this->agent);
        $text = fn (string $key): string => Text::get('demo.custom_slide.'.$key);

        $slide = app(SaveCustomSlide::class)->handle($slide, new CustomSlideInput(
            $text('slide_title'),
            CustomSlideLayout::TwoColumns,
            SlideSection::BRAND_EXPOSURE,
            null,
            CustomSlideContent::normalize([
                ['key' => 'tit', 'type' => 'title', 'column' => 'left', 'text' => $text('title')],
                ['key' => 'txt', 'type' => 'text', 'column' => 'left', 'text' => $text('text')],
                ['key' => 'tab', 'type' => 'table', 'column' => 'right', 'rows' => [
                    [$text('table_activity'), $text('table_when')],
                    [$text('row_1'), $text('row_1_when')],
                    [$text('row_2'), $text('row_2_when')],
                    [$text('row_3'), $text('row_3_when')],
                ]],
                ['key' => 'img', 'type' => 'image', 'column' => 'right'],
            ]),
        ), $this->agent);

        app(AttachCustomSlideImage::class)->handle($slide, $this->upload($this->artwork->chart(), 'grafico.png'), 'img', $this->agent);
    }

    private function group(Proposal $proposal): ProposalGroup
    {
        return ProposalGroup::query()->findOrFail($proposal->refresh()->proposal_group_id);
    }

    private function listTotal(Proposal $proposal): string
    {
        $total = $proposal->workingVersion()->value('list_total');

        return Amounts::italian(is_string($total) ? $total : '0.00');
    }

    private function competition(string $code): Competition
    {
        return Competition::query()->where('code', $code)->firstOrFail();
    }

    private function format(string $code): LedFormat
    {
        return LedFormat::query()->where('code', $code)->firstOrFail();
    }

    private function tier(string $code): PartnershipTier
    {
        return PartnershipTier::query()->where('code', $code)->firstOrFail();
    }

    private function seatVariant(string $code): HospitalityVariant
    {
        return HospitalityVariant::query()->where('code', $code)->firstOrFail();
    }

    private function firstAdmin(): ?User
    {
        return User::query()->where('role', Role::Admin->value)->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * The fonts of the documents for the drawn texts (Interstate on the VM, DejaVu otherwise); none
     * if not available (GD's own font).
     *
     * @return array<int, string>
     */
    private function fonts(): array
    {
        try {
            return app(DocumentFonts::class)->files();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * A file of the server given to an Action as if uploaded (the Actions check and re-encode it).
     */
    private function upload(string $contents, string $name): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * A horizontal LED row across the whole photo: four points from the top left, clockwise.
     *
     * @param  array{int, int}  $rows
     * @return list<array{int, int}>
     */
    private static function area(array $rows): array
    {
        [$top, $bottom] = $rows;

        return [[0, $top], [DemoData::FIELD_WIDTH, $top], [DemoData::FIELD_WIDTH, $bottom], [0, $bottom]];
    }

    /** Base price × level %, as a decimal string (whole euro, C5). */
    private static function amount(int $price, int $level): string
    {
        return intdiv($price * $level, 100).'.'.str_pad((string) ($price * $level % 100), 2, '0', STR_PAD_LEFT);
    }
}
