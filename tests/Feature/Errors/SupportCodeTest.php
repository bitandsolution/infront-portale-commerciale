<?php

use App\Support\SupportCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
| System errors with a support code (ux-architettura §4.5 "Errore di sistema", D-265).
|
| The failing route exists only inside these tests (it replaced the error button of the
| layout preview, removed by PR-03 with D-264).
*/

const FAILING_URL = '/_test/errore-di-sistema';

beforeEach(function () {
    Route::middleware('web')->post(FAILING_URL, function (): never {
        throw new RuntimeException('Simulated system error.');
    });
});

it('D-265: sends an Inertia visit back with an error toast carrying the support code', function () {
    $from = route('login');

    $response = $this->from($from)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(FAILING_URL);

    $response->assertRedirect($from)->assertSessionHas('toast');

    $toast = session('toast');
    expect($toast['type'])->toBe('error')
        ->and($toast['message'])->toBe(__('ui.errors.system'))
        ->and($toast['code'])->toMatch(SupportCode::pattern())
        ->and($toast['id'])->not->toBeEmpty();
});

it('D-265: writes the same support code in the log', function () {
    Log::spy();

    $this->from(route('login'))
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(FAILING_URL);

    $code = session('toast')['code'];

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => ($context['support_code'] ?? null) === $code)
        ->once();
});

it('D-265: shares the flashed toast with the next page', function () {
    $toast = ['id' => 'abc', 'type' => 'error', 'message' => 'Errore', 'code' => 'K7Q2M9'];

    $this->withSession(['toast' => $toast])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('toast.code', 'K7Q2M9'));
});

it('D-265: shows the support code on the error page of a full page load', function () {
    config(['app.debug' => false]);

    $response = $this->post(FAILING_URL);

    $response->assertStatus(500)->assertSee(__('ui.errors.page_title'));
    expect($response->getContent())->toMatch('/<code>[A-HJ-NP-Z2-9]{6}<\/code>/');
});

it('D-265: keeps other HTTP errors unchanged', function () {
    $this->withHeaders(['X-Inertia' => 'true'])
        ->get('/pagina-che-non-esiste')
        ->assertNotFound();
});
