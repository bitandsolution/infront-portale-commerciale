<?php

namespace App\Domain\Documents\Exceptions;

use App\Support\Text;
use RuntimeException;

/**
 * The generated PDF does not pass the checks of M09 §9 (number of pages, linearisation, fonts
 * embedded as subsets). The message is the Italian text for the operator (lang/it/documents.php),
 * reason the stable key for tests and logs.
 */
final class DocumentCheckFailed extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    private function __construct(public readonly string $reason, array $replace = [])
    {
        parent::__construct(Text::get('documents.errors.'.$reason, $replace));
    }

    public static function pageCount(int $pages, int $expected): self
    {
        return new self('page_count', ['pages' => (string) $pages, 'expected' => (string) $expected]);
    }

    public static function notLinearized(): self
    {
        return new self('not_linearized');
    }

    public static function fontNotEmbedded(string $font): self
    {
        return new self('font_not_embedded', ['font' => $font]);
    }
}
