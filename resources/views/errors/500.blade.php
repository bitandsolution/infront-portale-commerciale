<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('ui.errors.page_title') }}</title>
        {{-- Self-contained on purpose: the asset build may be the cause of the error. --}}
        <style nonce="{{ Vite::cspNonce() }}">
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f6f8; color: #15171c; font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif; }
            main { max-width: 32rem; margin: 1.5rem; padding: 2rem; background: #fff; border: 1px solid #e1e4e9; border-radius: 14px; }
            .brand { font-weight: 700; font-size: 22px; color: #c8102e; letter-spacing: -0.5px; }
            h1 { font-size: 20px; font-weight: 600; margin: 1rem 0 0.5rem; }
            p { line-height: 1.5; color: #3d4350; }
            code { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 15px; color: #15171c; }
            a { color: #b0102a; font-weight: 600; }
        </style>
    </head>
    <body>
        <main>
            <div class="brand">{{ __('ui.brand') }}</div>
            <h1>{{ __('ui.errors.page_title') }}</h1>
            <p>{{ __('ui.errors.system') }} ({{ __('ui.toast.code') }} <code>{{ app(\App\Support\SupportCode::class)->value() }}</code>).</p>
            <p><a href="{{ url('/') }}">{{ __('ui.errors.back_home') }}</a></p>
        </main>
    </body>
</html>
