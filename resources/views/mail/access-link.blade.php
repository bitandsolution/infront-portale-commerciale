<x-mail::message>
# {{ $greeting }}

{{ $intro }}

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>

{{ $expiry }}

{{ $ignore }}
</x-mail::message>
