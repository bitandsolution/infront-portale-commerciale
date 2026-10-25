<?php

use App\Domain\Documents\Exceptions\DocumentToolUnavailable;
use App\Domain\Documents\Services\Chromium;

/*
| Chromium's sandbox (R1, D-308): required everywhere; it can be turned off only in local and testing,
| where Docker's seccomp profile does not let Chromium create it (Sail on the VM).
*/

it('R1: keeps the Chromium sandbox when DOCUMENT_CHROMIUM_NO_SANDBOX is not set', function () {
    config(['documents.chromium.no_sandbox' => false]);

    expect(app(Chromium::class)->sandboxDisabled())->toBeFalse();
});

it('D-308: turns the sandbox off in local, where Sail runs Chromium in Docker', function () {
    config(['documents.chromium.no_sandbox' => true]);
    app()->detectEnvironment(fn (): string => 'local');

    expect(app(Chromium::class)->sandboxDisabled())->toBeTrue();
});

it('R1: refuses to turn the sandbox off in staging and production', function (string $environment) {
    config(['documents.chromium.no_sandbox' => true]);
    app()->detectEnvironment(fn (): string => $environment);

    try {
        app(Chromium::class)->sandboxDisabled();
        $this->fail('The sandbox was turned off in '.$environment.'.');
    } catch (DocumentToolUnavailable $exception) {
        expect($exception->reason)->toBe('sandbox_required')
            ->and($exception->getMessage())->toContain($environment);
    }
})->with(['staging', 'production']);
