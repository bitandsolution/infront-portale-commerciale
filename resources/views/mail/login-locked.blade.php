{{-- Lock after 5 failed attempts, to the user (App\Mail\LoginLockedMail; M01 §7, D-274). --}}
<x-mail::message>
# {{ $greeting }}

{{ $intro }}

{{ $yours }}

**{{ $notYours }}**

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>
</x-mail::message>
