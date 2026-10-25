<?php

namespace App\Domain\Sports\Data;

use App\Enums\SeasonStatus;
use Illuminate\Support\Carbon;

/**
 * Data of a season from the Seasons page (M02 F-03). The code is used only on creation: it is a
 * stable identifier (C3) and does not change afterwards.
 */
final readonly class SeasonData
{
    public function __construct(
        public string $code,
        public string $name,
        public Carbon $startsOn,
        public Carbon $endsOn,
        public SeasonStatus $status,
    ) {}
}
