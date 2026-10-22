<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
            ],
            // Interface texts for the frontend: single source in lang/it/ui.php (CLAUDE.md §9).
            'ui' => fn (): mixed => trans('ui'),
            // One-shot notification (ux-architettura §4.5): {id, type, message, code?}.
            'toast' => fn (): mixed => $request->hasSession() ? $request->session()->get('toast') : null,
        ];
    }
}
