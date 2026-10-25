{{-- Queue waiting longer than 10 minutes, to the admins (App\Mail\QueueStalledMail; §5.2.7, D-298). --}}
<x-mail::message>
# {{ $greeting }}

{{ $intro }}

{{ $note }}

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>
</x-mail::message>
