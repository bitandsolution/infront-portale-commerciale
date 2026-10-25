{{-- Access link (App\Mail\AccessLinkMail): texts from lang/it/mail.php, link never stored. --}}
<x-mail::message>
# {{ $greeting }}

{{ $intro }}

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>

{{ $expiry }}

{{ $ignore }}

<x-slot:subcopy>
{{ __('mail.button_fallback') }} <span class="break-all">{{ $url }}</span>
</x-slot:subcopy>
</x-mail::message>
