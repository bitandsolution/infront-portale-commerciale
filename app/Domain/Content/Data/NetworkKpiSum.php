<?php

namespace App\Domain\Content\Data;

/**
 * Sum of a KPI over the clubs of a network document (M08 F-06, RB-05; D-490, D-501): only the clubs
 * with the value are summed, the others are listed as missing.
 */
final readonly class NetworkKpiSum
{
    /**
     * @param  list<int>  $clubIds  clubs summed
     * @param  list<int>  $missingClubIds  clubs without the value
     */
    public function __construct(
        public string $code,
        public string $name,
        public int $sum,
        public array $clubIds,
        public array $missingClubIds,
    ) {}

    /**
     * @return array{name: string, sum: int, clubs: list<int>, missing: list<int>}
     */
    public function toSnapshot(): array
    {
        return [
            'name' => $this->name,
            'sum' => $this->sum,
            'clubs' => $this->clubIds,
            'missing' => $this->missingClubIds,
        ];
    }
}
