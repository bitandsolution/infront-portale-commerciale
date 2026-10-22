<?php

use App\Http\Controllers\Preview\LayoutPreviewController;
use App\Http\Middleware\EnsureLayoutPreviewEnabled;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Placeholder home page for the skeleton (PR-00). Replaced by the role dashboards in later PRs.
Route::get('/', fn () => Inertia::render('Home', [
    'title' => __('app.home.title'),
    'subtitle' => __('app.home.subtitle'),
    'previewUrl' => EnsureLayoutPreviewEnabled::enabled()
        ? route('preview.layout', ['role' => 'agente'])
        : null,
]))->name('home');

// Layout preview (PR-01, D-264): local and testing environments only, removed when users exist.
if (EnsureLayoutPreviewEnabled::enabled()) {
    Route::middleware(EnsureLayoutPreviewEnabled::class)
        ->prefix('anteprima')
        ->name('preview.')
        ->group(function (): void {
            Route::get('layout/{role}', [LayoutPreviewController::class, 'show'])
                ->whereIn('role', array_keys(LayoutPreviewController::ROLES))
                ->name('layout');
            Route::post('errore', [LayoutPreviewController::class, 'error'])
                ->name('error');
        });
}
