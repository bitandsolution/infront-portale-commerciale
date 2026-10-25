<?php

namespace App\Domain\Documents\Data;

/**
 * Metadata written into the PDF by qpdf (M09 §7): title, author "Infront Italy", season as subject.
 */
final readonly class PdfMetadata
{
    public const AUTHOR = 'Infront Italy';

    public function __construct(
        public string $title,
        public string $subject,
        public string $author = self::AUTHOR,
    ) {}
}
