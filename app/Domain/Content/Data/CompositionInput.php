<?php

namespace App\Domain\Content\Data;

use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\Season;

/**
 * Input of the composition of a document (M09 §6 step 3; D-396): the season and the variants of a
 * group with their clubs, and the slides chosen for the document (D-418, D-419). Built from a group
 * of proposals, or by hand (tests, future simulator, M08 F-04), so the composition has one
 * implementation (CLAUDE.md §4).
 */
final readonly class CompositionInput
{
    /**
     * @param  list<VariantFacts>  $variants
     */
    public function __construct(
        public ?Season $season,
        public array $variants,
        public DocumentSelection $selection = new DocumentSelection,
    ) {}

    /**
     * The variants of a group, each from its working version or, without it, from its last frozen
     * version (the approved ones, DM-03); the working choice of the slides of the group (D-419).
     */
    public static function fromGroup(ProposalGroup $group): self
    {
        $variants = [];

        foreach ($group->proposals()->with(['workingVersion', 'currentVersion'])->get() as $proposal) {
            $version = $proposal->workingVersion ?? $proposal->currentVersion;
            $variants[] = new VariantFacts(
                (string) $proposal->variant_letter,
                $version !== null ? self::clubsOf($version) : [],
            );
        }

        return new self($group->season, $variants, $group->documentSelection());
    }

    /**
     * Given versions of the variants of a group, by letter (DM-04: the frozen versions of the
     * approved variants, D-414), with the choice of the slides frozen on the first one (D-419: the
     * same on every variant of the group).
     *
     * @param  array<string, ProposalVersion>  $versions  letter → version
     */
    public static function fromVersions(?Season $season, array $versions): self
    {
        $variants = [];

        $selection = null;

        foreach ($versions as $letter => $version) {
            $variants[] = new VariantFacts((string) $letter, self::clubsOf($version));
            $selection ??= $version->documentSelection();
        }

        return new self($season, $variants, $selection ?? new DocumentSelection);
    }

    /**
     * Distinct clubs of all the variants, in the order they first appear (D-403).
     *
     * @return list<int>
     */
    public function clubIds(): array
    {
        $ids = [];

        foreach ($this->variants as $variant) {
            foreach ($variant->clubs as $club) {
                $ids[$club->clubId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Every club row of every variant.
     *
     * @return list<ClubFacts>
     */
    public function clubRows(): array
    {
        $rows = [];

        foreach ($this->variants as $variant) {
            array_push($rows, ...$variant->clubs);
        }

        return $rows;
    }

    /**
     * @return list<ClubFacts>
     */
    private static function clubsOf(ProposalVersion $version): array
    {
        return array_values($version->clubs()
            ->with(['partnershipTier', 'ledFormat', 'edition.competition', 'seats'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ProposalVersionClub $row): ClubFacts => new ClubFacts(
                clubId: $row->club_id,
                tierCode: $row->partnershipTier?->code,
                hasPartnership: $row->partnershipTier !== null && ! $row->partnershipTier->is_free_package,
                formatCode: $row->led_format_id !== null && ($row->led_seconds_per_match ?? 0) > 0 ? $row->ledFormat?->code : null,
                hasHospitality: $row->seats->sum('seats') > 0,
                competitionTier: $row->edition?->competition->tier,
            ))
            ->all());
    }
}
