<?php

namespace App\Domain\Content\Data;

use App\Enums\SlideConditionType;

/**
 * What a club row of a variant offers, for the inclusion conditions of the slides (M08 §4.2, D-202):
 * package, partnership other than the Libera, LED format, hospitality seats, tier of the competition.
 */
final readonly class ClubFacts
{
    public function __construct(
        public int $clubId,
        public ?string $tierCode,
        public bool $hasPartnership,
        public ?string $formatCode,
        public bool $hasHospitality,
        public ?int $competitionTier,
    ) {}

    public function hasLed(): bool
    {
        return $this->formatCode !== null;
    }

    /**
     * True when the club satisfies one of the values of a condition type.
     *
     * @param  list<string>  $values
     */
    public function satisfies(SlideConditionType $type, array $values): bool
    {
        $own = match ($type) {
            SlideConditionType::PartnershipTier => $this->tierCode,
            SlideConditionType::HasPartnership => self::boolean($this->hasPartnership),
            SlideConditionType::LedFormat => $this->formatCode,
            SlideConditionType::HasLed => self::boolean($this->hasLed()),
            SlideConditionType::HasHospitality => self::boolean($this->hasHospitality),
            SlideConditionType::CompetitionTier => $this->competitionTier !== null ? (string) $this->competitionTier : null,
        };

        return $own !== null && in_array($own, $values, true);
    }

    private static function boolean(bool $value): string
    {
        return $value ? SlideConditionType::TRUE : SlideConditionType::FALSE;
    }
}
