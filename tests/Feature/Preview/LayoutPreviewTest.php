<?php

use Inertia\Testing\AssertableInertia as Assert;

/*
| Layout preview before users exist (PR-01, D-264): local and testing environments only.
*/

it('D-264: renders the layout preview for each role', function (string $role, string $label) {
    $this->get(route('preview.layout', ['role' => $role]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Preview/Layout')
            ->where('layout.title', 'Dashboard')
            ->where('layout.user.role', $label)
            ->where('layout.season.label', '2027-28')
            ->where('layout.season.enabled', false)
            ->has('layout.navigation')
            ->has('roles', 3)
            ->has('ui.menu')
        );
})->with([
    'agente' => ['agente', 'Agente'],
    'gestore' => ['gestore', 'Gestore'],
    'admin' => ['admin', 'Admin'],
]);

it('D-264: sends only the menu of the requested role', function () {
    $this->get(route('preview.layout', ['role' => 'gestore']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('layout.navigation', fn ($items) => collect($items)->pluck('key')->doesntContain('users')
                && collect($items)->pluck('key')->contains('catalog'))
        );
});

it('D-264: marks the selected menu entry as the current page', function () {
    $this->get(route('preview.layout', ['role' => 'admin', 'voce' => 'catalog_led']))
        ->assertInertia(fn (Assert $page) => $page->where('layout.title', 'Listini LED'));
});

it('D-264: ignores an unknown menu entry', function () {
    $this->get(route('preview.layout', ['role' => 'admin', 'voce' => 'unknown']))
        ->assertInertia(fn (Assert $page) => $page->where('layout.title', 'Dashboard'));
});

it('D-264: answers 404 for an unknown role', function () {
    $this->get('/anteprima/layout/ospite')->assertNotFound();
});

it('D-264: answers 404 outside the local and testing environments', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/anteprima/layout/admin')->assertNotFound();
    $this->post('/anteprima/errore')->assertNotFound();
});

it('links the home page to the preview only where the preview exists', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('previewUrl', route('preview.layout', ['role' => 'agente'])));
});
