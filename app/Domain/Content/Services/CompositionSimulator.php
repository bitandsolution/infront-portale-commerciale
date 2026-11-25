<?php

namespace App\Domain\Content\Services;

use App\Domain\Catalog\Services\PriceListGrid;
use App\Domain\Content\Data\ClubFacts;
use App\Domain\Content\Data\Composition;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Data\DocumentSelection;
use App\Domain\Content\Data\SimulatedClub;
use App\Domain\Content\Data\VariantFacts;
use App\Enums\CompetitionType;
use App\Enums\DocumentMode;
use App\Models\Club;
use App\Models\ClubEnrollment;
use App\Models\Competition;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Season;
use Illuminate\Support\Collection;

/**
 * Simulator of the composition of a document (M08 F-04, FL-01, CA5; D-118, D-404, D-494…D-498): a
 * proposal of one variant built by hand from the choices of admin or manager, composed by
 * DocumentComposer (the only implementation, CLAUDE.md §4). Nothing is saved.
 *
 * - Clubs: the Infront clubs enrolled in a competition of the season (PriceListGrid), with the tier of
 *   their championship (D-496); a club chosen twice counts once, an unknown club is ignored.
 * - Packages and formats: the active values of the catalogue, not only those of the price list, so
 *   the slides can be prepared before the publication (D-496); an unknown code counts as none.
 * - One variant, "A" (D-497); the mode chooses "Documento completo" or "Solo riepilogo" (D-397).
 */
final class CompositionSimulator
{
    public function __construct(
        private readonly DocumentComposer $composer,
        private readonly PriceListGrid $grid,
    ) {}

    /**
     * @return Collection<int, Club>
     */
    public function clubs(Season $season): Collection
    {
        return $this->grid->clubs($season);
    }

    /**
     * Active packages, the Libera included (a package without partnership, D-052).
     *
     * @return Collection<int, PartnershipTier>
     */
    public function tiers(): Collection
    {
        return PartnershipTier::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, LedFormat>
     */
    public function formats(): Collection
    {
        return $this->grid->ledFormats();
    }

    /**
     * The championship of a club in the season (one per club and season, M02): a league before a cup.
     */
    public static function competitionOf(Club $club): ?Competition
    {
        $competitions = $club->enrollments
            ->map(fn (ClubEnrollment $enrollment): Competition => $enrollment->competitionSeason->competition)
            ->sortBy(fn (Competition $competition): int => $competition->type === CompetitionType::League ? 0 : 1);

        return $competitions->first();
    }

    /**
     * @param  list<SimulatedClub>  $choices
     */
    public function compose(Season $season, array $choices, DocumentMode $mode): Composition
    {
        $clubs = $this->clubs($season)->keyBy('id');
        $tiers = $this->tiers()->keyBy('code');
        $formats = $this->formats()->keyBy('code');
        $facts = [];

        foreach ($choices as $choice) {
            $club = $clubs->get($choice->clubId);

            if (! $club instanceof Club || isset($facts[$club->id])) {
                continue;
            }

            $tier = $choice->tierCode !== null ? $tiers->get($choice->tierCode) : null;
            $format = $choice->formatCode !== null ? $formats->get($choice->formatCode) : null;

            $facts[$club->id] = new ClubFacts(
                clubId: $club->id,
                tierCode: $tier instanceof PartnershipTier ? $tier->code : null,
                hasPartnership: $tier instanceof PartnershipTier && ! $tier->is_free_package,
                formatCode: $format instanceof LedFormat ? $format->code : null,
                hasHospitality: $choice->hospitality,
                competitionTier: self::competitionOf($club)?->tier,
            );
        }

        return $this->composer->compose(new CompositionInput(
            $season,
            [new VariantFacts('A', array_values($facts))],
            new DocumentSelection($mode),
        ));
    }
}
