<?php

namespace App\Domain\Content\Data;

use Illuminate\Support\Carbon;

/**
 * A KPI in force of a club in a season (M08 §5, RB-04; D-203, D-491, D-493): the value with its source,
 * reference period and date of the last update, and the name of the definition at that moment, so a
 * document keeps them even if they change later (D-111, D-204, D-500).
 */
final readonly class KpiFigure
{
    public function __construct(
        public string $code,
        public string $name,
        public int $value,
        public string $source,
        public string $period,
        public Carbon $updatedAt,
    ) {}

    /**
     * The form saved in the KPI snapshot of a document (D-500).
     *
     * @return array{name: string, value: int, source: string, period: string, updated_at: string}
     */
    public function toSnapshot(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'source' => $this->source,
            'period' => $this->period,
            'updated_at' => $this->updatedAt->copy()->utc()->toIso8601String(),
        ];
    }
}
