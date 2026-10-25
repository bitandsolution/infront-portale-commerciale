<?php

use App\Enums\FilePurpose;
use App\Http\Middleware\AddSecurityHeaders;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
| Security headers (progettazione-tecnica §5.2.4; D-176; PR-09): on every answer, with an enforced
| Content-Security-Policy with a nonce on the HTML pages, a wider policy on the Horizon panel only
| and HSTS on HTTPS only, without includeSubDomains and preload (domain still open, D-180).
*/

/**
 * Directives of the Content-Security-Policy of a response, by name.
 *
 * @return array<string, string>
 */
function securityPolicy(TestResponse $response): array
{
    $directives = [];
    foreach (explode(';', (string) $response->headers->get('Content-Security-Policy')) as $directive) {
        $parts = explode(' ', trim($directive), 2);
        if ($parts[0] !== '') {
            $directives[$parts[0]] = $parts[1] ?? '';
        }
    }

    return $directives;
}

function securityNonce(TestResponse $response): string
{
    preg_match("/'nonce-([^']+)'/", securityPolicy($response)['script-src'] ?? '', $match);

    return $match[1] ?? '';
}

it('D-176: every answer has the security headers', function (string $url) {
    $response = $this->get($url);

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy', AddSecurityHeaders::PERMISSIONS_POLICY)
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
})->with(['login page' => '/login', 'health' => '/health', 'missing page' => '/pagina-inesistente']);

it('D-176: the pages have an enforced policy with a nonce, without inline scripts nor outside resources', function () {
    $response = $this->get(route('login'));
    $policy = securityPolicy($response);
    $nonce = securityNonce($response);

    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($nonce)->toMatch('/^[A-Za-z0-9]{40}$/')
        ->and($policy)->toBe([
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-{$nonce}'",
            'style-src' => "'self' 'nonce-{$nonce}'",
            'font-src' => "'self'",
            'img-src' => "'self' data: blob:",
            'connect-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'none'",
        ]);
});

it('D-176: the nonce reaches the scripts of the page and Inertia through the meta tag', function () {
    $response = $this->get(route('login'));
    $nonce = securityNonce($response);
    $html = (string) $response->getContent();

    expect($html)->toContain('<meta name="csp-nonce" content="'.$nonce.'">');

    preg_match_all('/<script\b[^>]*>/', $html, $scripts);
    foreach ($scripts[0] as $tag) {
        // Inertia's page data is JSON, never executed.
        if (! str_contains($tag, 'application/json')) {
            expect($tag)->toContain('nonce="'.$nonce.'"');
        }
    }
});

it('D-176: every request has a new nonce', function () {
    expect(securityNonce($this->get(route('login'))))->not->toBe(securityNonce($this->get(route('login'))));
});

it('D-176: the inline style of the error pages carries the nonce', function () {
    config(['app.debug' => false]);
    Route::get('/_test/errore-intestazioni', function (): never {
        throw new RuntimeException('Simulated system error.');
    });

    $response = $this->get('/_test/errore-intestazioni');

    $response->assertStatus(500);
    expect((string) $response->getContent())->toContain('<style nonce="'.securityNonce($response).'">');

    // With APP_DEBUG on (development only) Laravel's detailed page has no policy, so it stays readable.
    config(['app.debug' => true]);
    expect($this->get('/_test/errore-intestazioni')->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('D-176: Laravel\'s own error pages (404…) use the portal layout, with the nonce and in Italian', function () {
    $response = $this->get('/pagina-inesistente');

    $response->assertNotFound()->assertSee('Pagina non trovata');
    expect((string) $response->getContent())->toContain('<style nonce="'.securityNonce($response).'">');
});

it('D-298: only the Horizon panel allows eval, inline styles and its font', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/horizon');
    $policy = securityPolicy($response);
    $nonce = securityNonce($response);

    $response->assertOk();
    expect($policy['script-src'])->toBe("'self' 'nonce-{$nonce}' 'unsafe-eval'")
        ->and($policy['style-src'])->toBe("'self' 'unsafe-inline' https://fonts.bunny.net")
        ->and($policy['font-src'])->toBe("'self' https://fonts.bunny.net")
        ->and($policy['frame-ancestors'])->toBe("'none'")
        ->and((string) $response->getContent())->toContain('<script type="module" nonce="'.$nonce.'">');

    expect(securityPolicy($this->actingAs($admin)->get(route('pages.dashboard')))['script-src'])->not->toContain('unsafe-eval');
});

it('D-176: JSON answers and downloads keep their own policy or none', function () {
    Storage::fake(StoredFile::DISK);
    $admin = User::factory()->admin()->create();
    $file = storeTestFile(FilePurpose::ClientLogo);

    expect($this->get('/health')->headers->has('Content-Security-Policy'))->toBeFalse();

    $this->actingAs($admin)->get(route('files.download', $file))
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
});

it('D-176, D-180: HSTS only on HTTPS, one year, without subdomains and preload', function () {
    expect($this->get(route('login'))->headers->has('Strict-Transport-Security'))->toBeFalse();

    $this->get(str_replace('http://', 'https://', route('login')))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
