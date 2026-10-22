<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Placeholder home page for the skeleton (PR-00). Replaced by the role dashboards in later PRs.
Route::get('/', fn () => Inertia::render('Home', [
    'title' => __('app.home.title'),
    'subtitle' => __('app.home.subtitle'),
]))->name('home');
