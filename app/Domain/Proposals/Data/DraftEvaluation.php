<?php

namespace App\Domain\Proposals\Data;

/**
 * A draft evaluated by the server (F4-03): one evaluation per club in the order of the draft and the
 * list total, the sum of the club prices that can be calculated (M06 RB-07; only list prices, never
 * discounted: D-011, D-103).
 */
final readonly class DraftEvaluation
{
    /**
     * @param  list<ClubEvaluation>  $clubs
     */
    public function __construct(
        public array $clubs,
        public string $listTotal,
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
}
