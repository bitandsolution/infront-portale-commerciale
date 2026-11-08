<?php

namespace App\Domain\Proposals\Data;

use Illuminate\Support\Carbon;

/**
 * Result of an automatic save (D-165, D-372): whether something changed, the new lock version the
 * page sends with the next save, and the moment of the last saved change ("Bozza salvata alle hh:mm").
 */
final readonly class DraftSaved
{
    public function __construct(
        public bool $changed,
        public int $lockVersion,
        public Carbon $savedAt,
    ) {}
}
