<?php

namespace App\Domain\Content\Data;

/**
 * A paragraph or bulleted item of a text block (CustomText), as runs of normal and bold text.
 */
final readonly class CustomParagraph
{
    /**
     * @param  list<array{text: string, bold: bool}>  $runs
     */
    public function __construct(
        public bool $bullet,
        public array $runs,
    ) {}

    public function isEmpty(): bool
    {
        return $this->runs === [];
    }
}
