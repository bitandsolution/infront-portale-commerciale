<?php

namespace App\Domain\Documents\Data;

/**
 * A font of a PDF, as listed by pdffonts: name (with the "ABCDEF+" prefix when subset), embedded,
 * subset (M09 §7: fonts embedded, only the characters used).
 */
final readonly class PdfFont
{
    public function __construct(
        public string $name,
        public bool $embedded,
        public bool $subset,
    ) {}
}
