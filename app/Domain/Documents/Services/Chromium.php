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
 * Isolation from the network (D-312, D-176), on three levels:
 * 1. the page is a file in the folder of the job and its fonts and images are file:// resources;
 *    JavaScript is disabled;
 * 2. Chromium goes through a proxy that does not exist (127.0.0.1:9), loopback included, so any
 *    request to a network address fails;
 * 3. Browsershot aborts every request to a network scheme before it leaves the page.
 */
final class Chromium
{
    /**
     * A proxy nobody listens to: every network request of Chromium fails (D-312, level 2).
     */
    public const BLACKHOLE_PROXY = '127.0.0.1:9';

    /**
     * Requests aborted by Browsershot before they leave the page (D-312, level 3).
     *
     * @var list<string>
     */
    public const BLOCKED_URLS = ['http://', 'https://', 'ws://', 'wss://', 'ftp://'];

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
            ->timeout($this->timeout())
            ->disableJavascript()
            ->setProxyServer(self::BLACKHOLE_PROXY)
            ->addChromiumArguments(['proxy-bypass-list' => '<-loopback>'])
            ->blockUrls(self::BLOCKED_URLS);

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
