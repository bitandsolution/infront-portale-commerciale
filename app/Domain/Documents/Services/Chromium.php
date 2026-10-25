<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\DocumentToolUnavailable;
use Illuminate\Contracts\Foundation\Application;
use Spatie\Browsershot\Browsershot;

/**
 * Chromium headless through Browsershot, configured for this machine (D-220, D-308): Node.js and npm
 * binaries, timeout, sandbox. Browsershot launches chrome-headless-shell, which puppeteer downloads
 * into .cache/puppeteer with `npm ci` (.puppeteerrc.cjs).
 *
 * The isolation from the network of D-312 and the page model of the documents arrive with S1-02.
 */
final class Chromium
{
    public function __construct(private readonly Application $app) {}

    public function browsershot(string $html): Browsershot
    {
        $browsershot = Browsershot::html($html)
            ->setNodeBinary($this->stringConfig('documents.chromium.node_binary', 'node'))
            ->setNpmBinary($this->stringConfig('documents.chromium.npm_binary', 'npm'))
            ->timeout($this->timeout());

        if ($this->sandboxDisabled()) {
            $browsershot->noSandbox();
        }

        return $browsershot;
    }

    /**
     * True when Chromium runs without its sandbox: only where Docker does not allow it (Sail on the
     * VM, local and testing environments) and only if DOCUMENT_CHROMIUM_NO_SANDBOX asks for it.
     * Anywhere else that setting stops the generation instead of being ignored (R1).
     */
    public function sandboxDisabled(): bool
    {
        if (config('documents.chromium.no_sandbox') !== true) {
            return false;
        }

        if (! $this->app->environment('local', 'testing')) {
            throw DocumentToolUnavailable::sandboxRequired((string) $this->app->environment());
        }

        return true;
    }

    private function timeout(): int
    {
        $timeout = config('documents.chromium.timeout');

        return is_int($timeout) && $timeout > 0 ? $timeout : 240;
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
