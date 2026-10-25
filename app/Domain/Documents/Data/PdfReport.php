<?php

namespace App\Domain\Documents\Data;

/**
 * Outcome of a generation (progettazione-tecnica §5.5.2, M09 §9): the optimised PDF, its pages and
 * weight, the time of each step in milliseconds, the fonts found in the file and the warnings
 * (for example a PDF heavier than 20 MB).
 */
final readonly class PdfReport
{
    /**
     * @param  array<string, int>  $timings  step => milliseconds
     * @param  list<PdfFont>  $fonts
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $path,
        public int $pages,
        public int $bytes,
        public array $timings,
        public array $fonts,
        public array $warnings,
    ) {}

    public function totalMilliseconds(): int
    {
        return array_sum($this->timings);
    }
}
