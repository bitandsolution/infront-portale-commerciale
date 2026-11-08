<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Catalog\Data\HospitalityLine;
use App\Domain\Proposals\Data\ClubPriceChange;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\DraftPriceChanges;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recalculation of a reopened draft (M06 RB-12, §9; D-045; M03-CA6 for the drafts; F4-04): the page
 * shows the evaluation on the current list and residuals (DraftEvaluator); this service compares it
 * with the prices saved on the rows at the last save, so the agent sees "da € X a € Y" for every club
 * whose price changed and for the list total. The rows are updated by the next automatic save
 * (SaveDraft), never by opening the page: reading a draft writes nothing.
 *
 * Only the clubs saved with a price and priced now are compared; a club that cannot be priced any
 * more has its own error (M06 §9).
 */
final class DraftRepricing
{
    /**
     * @param  Collection<int, ProposalVersionClub>  $rows  the saved rows, with their seats
     */
    public function compare(Collection $rows, string $savedListTotal, DraftEvaluation $evaluation): ?DraftPriceChanges
    {
        $clubs = [];

        foreach ($rows as $row) {
            $quote = $evaluation->club($row->club_id)?->quote;

            if ($row->club_total === null || $quote === null || self::same($row->club_total, $quote->total)) {
                continue;
            }

            $listChanged = ! self::same($row->led_unit_price, $quote->ledUnitPrice)
                || ($row->partnership_tier_id !== null && ! self::same($row->partnership_price ?? '0.00', $quote->partnershipPrice));

            foreach ($row->seats as $seat) {
                if (! self::same($seat->seat_price, self::seatPrice($seat, $quote->hospitality))) {
                    $listChanged = true;
                }
            }

            $clubs[] = new ClubPriceChange(
                club: $row->club,
                saved: $row->club_total,
                current: $quote->total,
                listChanged: $listChanged,
                matchesChanged: $row->led_format_id !== null && $row->home_matches !== $quote->matches->matches,
            );
        }

        if ($clubs === [] && self::same($savedListTotal, $evaluation->listTotal)) {
            return null;
        }

        return new DraftPriceChanges($clubs, $savedListTotal, $evaluation->listTotal);
    }

    /**
     * @param  list<HospitalityLine>  $lines
     */
    private static function seatPrice(ProposalVersionClubSeat $seat, array $lines): ?string
    {
        foreach ($lines as $line) {
            if ($line->variant->id === $seat->hospitality_variant_id) {
                return $line->seatPrice;
            }
        }

        return null;
    }

    private static function same(?string $saved, ?string $current): bool
    {
        if ($saved === null || $current === null) {
            return $saved === $current;
        }

        return BigDecimal::of($saved)->isEqualTo($current);
    }
}
