<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title')</title>
        {{--
            Layout of Laravel's own error pages (404, 419, 429, 503…), replacing the framework's one,
            whose inline style has no nonce and would be blocked by the Content-Security-Policy (PR-09).
            Self-contained like 500.blade.php. Titles in lang/it.json.
        --}}
        <style nonce="{{ Vite::cspNonce() }}">
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f6f8; color: #15171c; font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif; }
            main { max-width: 32rem; margin: 1.5rem; padding: 2rem; background: #fff; border: 1px solid #e1e4e9; border-radius: 14px; }
            .brand { font-weight: 700; font-size: 22px; color: #c8102e; letter-spacing: -0.5px; }
            h1 { font-size: 20px; font-weight: 600; margin: 1rem 0 0.5rem; }
            .code { font-family: 'IBM Plex Mono', ui-monospace, monospace; color: #6b7280; font-weight: 500; }
            p { line-height: 1.5; color: #3d4350; }
            a { color: #b0102a; font-weight: 600; }
        </style>
    </head>
    <body>
        <main>
            <div class="brand">{{ __('ui.brand') }}</div>
            <h1><span class="code">@yield('code')</span> · @yield('message')</h1>
            <p><a href="{{ url('/') }}">{{ __('ui.errors.back_home') }}</a></p>
        </main>
    </body>
</html>
