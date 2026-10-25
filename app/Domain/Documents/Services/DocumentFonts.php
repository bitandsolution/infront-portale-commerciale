<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\DocumentToolUnavailable;
use App\Enums\DocumentFontWeight;
use Illuminate\Contracts\Foundation\Application;

/**
 * Fonts of the generated documents (D-249, D-311): Interstate Regular, Bold and Black read from the
 * files in DOCUMENT_FONTS_PATH, or the free substitute (DejaVu) when that folder is not configured,
 * allowed only in the local and testing environments. The fonts are never installed in the system:
 * the HTML of the pages declares them with @font-face, a file:// source and an explicit weight.
 */
final class DocumentFonts
{
    /**
     * CSS family used by the layouts, the same for Interstate and for the substitute font.
     */
    public const FAMILY = 'DocumentFont';

    /**
     * First four bytes of a TrueType or OpenType font file.
     */
    private const SIGNATURES = ["\x00\x01\x00\x00", 'true', 'OTTO'];

    /**
     * Files already checked by this instance (one generation or one request).
     *
     * @var array<int, string>|null
     */
    private ?array $files = null;

    public function __construct(private readonly Application $app) {}

    /**
     * True when the documents use the substitute font instead of Interstate.
     */
    public function usesSubstitute(): bool
    {
        return $this->fontsPath() === null;
    }

    /**
     * Absolute path of the file of each weight, checked: it exists and it is a font file.
     *
     * @return array<int, string> CSS weight => absolute path
     */
    public function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $fontsPath = $this->fontsPath();

        if ($fontsPath === null && ! $this->app->environment('local', 'testing')) {
            throw DocumentToolUnavailable::fontsNotConfigured();
        }

        $files = [];

        foreach (DocumentFontWeight::cases() as $weight) {
            $path = $fontsPath === null
                ? $this->configuredFile('documents.substitute_font_files', $weight)
                : rtrim($fontsPath, '/').'/'.$this->configuredFile('documents.font_files', $weight);

            $files[$weight->value] = $this->checked($path);
        }

        return $this->files = $files;
    }

    /**
     * The @font-face rules of the three weights, for the <style> of the document pages.
     */
    public function css(): string
    {
        $rules = [];

        foreach ($this->files() as $weight => $path) {
            $rules[] = sprintf(
                "@font-face { font-family: '%s'; src: url('%s') format('%s'); font-weight: %d; font-style: normal; }",
                self::FAMILY,
                self::fileUrl($path),
                str_starts_with((string) file_get_contents($path, length: 4), 'OTTO') ? 'opentype' : 'truetype',
                $weight,
            );
        }

        return implode("\n", $rules);
    }

    /**
     * file:// URL of an absolute path, each segment percent-encoded (spaces, quotes, accents).
     */
    public static function fileUrl(string $path): string
    {
        $segments = array_map(rawurlencode(...), explode('/', ltrim($path, '/')));

        return 'file:///'.implode('/', $segments);
    }

    private function fontsPath(): ?string
    {
        $path = config('documents.fonts_path');

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    private function configuredFile(string $key, DocumentFontWeight $weight): string
    {
        $file = config($key.'.'.$weight->value);

        return is_string($file) ? $file : '';
    }

    private function checked(string $path): string
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw DocumentToolUnavailable::fontMissing($path);
        }

        $signature = (string) file_get_contents($path, length: 4);

        if (! in_array($signature, self::SIGNATURES, true)) {
            throw DocumentToolUnavailable::fontInvalid($path);
        }

        return $path;
    }
}
