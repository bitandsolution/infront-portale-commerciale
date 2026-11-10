<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Enums\MatchesSource;
use App\Models\PriceList;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use App\Models\Season;
use App\Models\Territory;
use App\Models\VisibilityType;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Writes the club rows of a working version and their hospitality seats from an evaluation of the
 * server (DraftEvaluator): matches and their source, list, unit prices and club price (CLAUDE.md §4,
 * prices never come from the page). Used by SaveDraft (F4-03) and by DuplicateProposal (F4-05); it
 * runs inside the transaction of the caller, which writes the audit event.
 */
final class DraftRowsWriter
{
    /**
     * Writes the clubs of the evaluation in its order; removes the rows of the clubs no longer in the
     * draft (their seats go with them, C3).
     *
     * @param  Collection<int, ProposalVersionClub>  $rows  the rows saved now (empty for a new version)
     */
    public function write(ProposalVersion $version, Collection $rows, DraftEvaluation $evaluation, ?Season $season): void
    {
        $kept = array_map(fn (ClubEvaluation $club): int => $club->club->id, $evaluation->clubs);

        foreach ($rows as $row) {
            if (! in_array($row->club_id, $kept, true)) {
                $row->delete();
            }
        }

        if ($evaluation->clubs === []) {
            return;
        }

        $priceListId = $season !== null ? PriceList::query()->where('season_id', $season->id)->value('id') : null;
        [$territoryId, $visibilityTypeId] = self::hiddenValues();

        foreach ($evaluation->clubs as $index => $club) {
            $row = $rows->firstWhere('club_id', $club->club->id) ?? new ProposalVersionClub([
                'proposal_version_id' => $version->id,
                'club_id' => $club->club->id,
                'territory_id' => $territoryId,
                'visibility_type_id' => $visibilityTypeId,
            ]);
            $quote = $club->quote;
            $data = $club->data;

            $row->fill([
                'sort_order' => $index,
                'partnership_tier_id' => $data->tierId,
                'led_format_id' => $data->ledFormatId,
                'led_seconds_per_match' => $data->ledFormatId !== null ? $data->ledSeconds : null,
                'competition_season_id' => $quote?->matches->edition->id,
                'home_matches' => $quote?->matches->matches,
                'matches_source' => $quote === null ? null : ($quote->matches->fromCalendar ? MatchesSource::Calendar : MatchesSource::Standard),
                'price_list_id' => $quote !== null ? $priceListId : null,
                'led_unit_price' => $quote?->ledUnitPrice,
                'led_price' => $quote?->ledPrice,
                'partnership_price' => $quote?->partnershipPrice,
                'hospitality_price' => $quote?->hospitalityPrice,
                'club_total' => $quote?->total,
            ])->save();

            $this->writeSeats($row, $club);
        }
    }

    /**
     * List total and KPI of the campaign of a working version from the evaluation (RB-07, D-093).
     *
     * @return array<string, int|string|null>
     */
    public static function versionTotals(DraftEvaluation $evaluation): array
    {
        $campaign = $evaluation->campaign;

        return [
            'list_total' => $evaluation->listTotal,
            'campaign_starts_on' => $campaign?->campaignStart?->toDateString(),
            'campaign_months' => $campaign?->campaignMonths,
            'total_matches' => $campaign?->totalMatches,
            'total_spots' => $campaign?->totalSpots,
            'top_matches' => $campaign?->topMatches,
        ];
    }

    private function writeSeats(ProposalVersionClub $row, ClubEvaluation $club): void
    {
        $saved = $row->seats()->get()->keyBy('hospitality_variant_id');
        $wanted = array_filter($club->data->seats, fn (int $seats): bool => $seats > 0);

        foreach ($saved as $variantId => $seat) {
            if (! array_key_exists((int) $variantId, $wanted)) {
                $seat->delete();
            }
        }

        foreach ($wanted as $variantId => $seats) {
            $line = null;

            foreach ($club->quote !== null ? $club->quote->hospitality : [] as $candidate) {
                if ($candidate->variant->id === $variantId) {
                    $line = $candidate;
                }
            }

            $seat = $saved->get($variantId) ?? new ProposalVersionClubSeat([
                'proposal_version_club_id' => $row->id,
                'hospitality_variant_id' => $variantId,
            ]);

            $seat->fill(['seats' => $seats, 'seat_price' => $line?->seatPrice, 'price' => $line?->price])->save();
        }
    }

    /**
     * D-085: territory and visibility type are hidden while a single value is active.
     *
     * @return array{int, int}
     */
    private static function hiddenValues(): array
    {
        $territory = Territory::query()->where('is_active', true)->orderBy('id')->value('id');
        $visibilityType = VisibilityType::query()->where('is_active', true)->orderBy('id')->value('id');

        if (! is_numeric($territory) || ! is_numeric($visibilityType)) {
            throw new RuntimeException('No active territory or visibility type: run SystemDataSeeder.');
        }

        return [(int) $territory, (int) $visibilityType];
    }
}
