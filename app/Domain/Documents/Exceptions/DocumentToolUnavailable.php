<?php

namespace App\Domain\Documents\Exceptions;

use App\Support\Text;
use RuntimeException;

/**
 * A tool of the document pipeline that is missing or misconfigured on this machine: the fonts
 * (D-249, D-311) or the Chromium sandbox (R1). It stops the generation; the message is the Italian
 * text for the operator (lang/it/documents.php), reason the stable key for tests and logs.
 */
final class DocumentToolUnavailable extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    private function __construct(public readonly string $reason, array $replace = [])
    {
        parent::__construct(Text::get('documents.errors.'.$reason, $replace));
    }

    public static function fontsNotConfigured(): self
    {
        return new self('fonts_not_configured');
    }

    public static function fontMissing(string $path): self
    {
        return new self('font_missing', ['path' => $path]);
    }

    public static function fontInvalid(string $path): self
    {
        return new self('font_invalid', ['path' => $path]);
    }

    public static function sandboxRequired(string $environment): self
    {
        return new self('sandbox_required', ['environment' => $environment]);
    }
}
