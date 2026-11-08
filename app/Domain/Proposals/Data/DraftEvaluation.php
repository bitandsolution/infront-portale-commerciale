<?php

namespace App\Domain\Proposals\Data;

/**
 * A draft evaluated by the server (F4-03): one evaluation per club in the order of the draft and the
 * list total, the sum of the club prices that can be calculated (M06 RB-07; only list prices, never
 * discounted: D-011, D-103), and from F4-04 the KPI of the campaign (null without a period or without
 * a club that can be priced).
 */
final readonly class DraftEvaluation
{
    /**
     * @param  list<ClubEvaluation>  $clubs
     */
    public function __construct(
        public array $clubs,
        public string $listTotal,
        public ?CampaignKpis $campaign = null,
    ) {}

    public function club(int $clubId): ?ClubEvaluation
    {
        foreach ($this->clubs as $club) {
            if ($club->club->id === $clubId) {
                return $club;
            }
        }

        return null;
    }

    public function hasErrors(): bool
    {
        foreach ($this->clubs as $club) {
            if ($club->hasErrors()) {
                return true;
            }
        }

        return false;
    }

    /**
     * D-373: the summary (step 5) opens only with at least one club and no club without elements.
     */
    public function blocksSummary(): bool
    {
        if ($this->clubs === []) {
            return true;
        }

        foreach ($this->clubs as $club) {
            if ($club->lacksElements()) {
                return true;
            }
        }

        return false;
    }
}
