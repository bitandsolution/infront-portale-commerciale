<?php

namespace App\Domain\Proposals\Data;

use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Support\LedDuration;
use Illuminate\Support\Collection;

/**
 * Fields of a draft in the audit events of the area "proposals" (D-372): the header of the steps 1
 * and 2 and, from F4-03, one field per club ("club_<club code>", the configuration in one line) and
 * the list total; from F4-04 the KPI of the campaign saved on the working version. The season is
 * written as season_code, because AuditLog drops the fields named
 * "code"; a club removed from the draft is written as null.
 */
final class ProposalsAuditValues
{
    /**
     * @return array<string, int|string|null>
     */
    public static function draft(Proposal $proposal, ProposalVersion $version): array
    {
        return [
            'number' => $proposal->number,
            'client_id' => $proposal->client_id,
            'client_name' => $proposal->client?->company_name,
            'season_id' => $proposal->season_id,
            'season_code' => $proposal->season?->code,
            'starts_on' => $version->starts_on?->toDateString(),
            'ends_on' => $version->ends_on?->toDateString(),
        ];
    }

    /**
     * The clubs of a working version as saved (rows with club, tier, format and seats loaded).
     *
     * @param  Collection<int, ProposalVersionClub>  $rows
     * @return array<string, string>
     */
    public static function clubRows(Collection $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $seats = [];

            foreach ($row->seats as $seat) {
                $seats[$seat->variant->code] = $seat->seats;
            }

            $values[self::clubKey($row->club->code)] = self::club($row->partnershipTier?->code, $row->ledFormat?->code, $row->led_seconds_per_match ?? 0, $seats);
        }

        return $values;
    }

    /**
     * The clubs of an evaluation (what the draft will hold after the save), one line per club.
     *
     * @return array<string, string>
     */
    public static function clubsOf(DraftEvaluation $evaluation): array
    {
        $tiers = PartnershipTier::query()->pluck('code', 'id');
        $formats = LedFormat::query()->pluck('code', 'id');
        $variants = HospitalityVariant::query()->pluck('code', 'id');
        $values = [];

        foreach ($evaluation->clubs as $club) {
            $seats = [];

            foreach ($club->data->seats as $variantId => $count) {
                $code = $variants->get($variantId);

                if ($count > 0 && is_string($code)) {
                    $seats[$code] = $count;
                }
            }

            $tier = $club->data->tierId !== null ? $tiers->get($club->data->tierId) : null;
            $format = $club->data->ledFormatId !== null ? $formats->get($club->data->ledFormatId) : null;

            $values[self::clubKey($club->club->code)] = self::club(
                is_string($tier) ? $tier : null,
                is_string($format) ? $format : null,
                $club->data->ledSeconds,
                $seats,
            );
        }

        return $values;
    }

    /**
     * KPI of the campaign saved on the working version (M06 §6, D-093; F4-04); null when they cannot
     * be calculated (no period, no club that can be priced) and top matches null when not available
     * or not applicable (D-320).
     *
     * @return array<string, int|string|null>
     */
    public static function campaign(ProposalVersion $version): array
    {
        return [
            'campaign_starts_on' => $version->campaign_starts_on?->toDateString(),
            'campaign_months' => $version->campaign_months,
            'total_matches' => $version->total_matches,
            'total_spots' => $version->total_spots,
            'top_matches' => $version->top_matches,
        ];
    }

    public static function clubKey(string $clubCode): string
    {
        return 'club_'.$clubCode;
    }

    /**
     * One club in one line: "TOP | FILA_2 1′00″ | PREMIUM 2, BASE 1", with "—" for what is missing.
     *
     * @param  array<string, int>  $seats  seats by variant code
     */
    public static function club(?string $tierCode, ?string $formatCode, int $seconds, array $seats): string
    {
        $hospitality = [];

        foreach ($seats as $code => $count) {
            $hospitality[] = $code.' '.$count;
        }

        return implode(' | ', [
            $tierCode ?? '—',
            $formatCode !== null ? $formatCode.' '.LedDuration::format($seconds) : '—',
            $hospitality !== [] ? implode(', ', $hospitality) : '—',
        ]);
    }
}
