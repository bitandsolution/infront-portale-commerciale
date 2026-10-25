<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Exceptions\DocumentToolUnavailable;
use Illuminate\Contracts\Foundation\Application;
use Spatie\Browsershot\Browsershot;

/**
 * Chromium headless through Browsershot, configured for this machine (D-220, D-308): Node.js binary,
 * puppeteer from the node_modules of the project (no global npm), timeout, sandbox. Browsershot
 * launches chrome-headless-shell, installed into .cache/puppeteer (.puppeteerrc.cjs).
 *
 * The isolation from the network of D-312 and the page model of the documents arrive with S1-02.
 */
final class Chromium
{
    public function __construct(private readonly Application $app) {}

    /**
     * Browsershot for a page already written to a file. The pages are always files in the folder of
     * the job, never HTML strings (D-312): their fonts and images are file:// resources next to
     * them, which Browsershot::html() refuses on purpose.
     */
    public function browsershot(string $htmlFile): Browsershot
    {
        $browsershot = Browsershot::htmlFromFilePath($htmlFile)
            ->setNodeBinary($this->stringConfig('documents.chromium.node_binary', 'node'))
            ->setNodeModulePath(base_path('node_modules'))
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
