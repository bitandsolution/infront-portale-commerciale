<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page as an Inertia page with Italian texts', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('title', 'Portale Commerciale Infront')
            ->where('app.locale', 'it')
        );
});

it('exposes the health endpoint', function () {
    $this->get('/up')->assertOk();
});
