{{-- Queued job failed after its last try, to the admins (App\Mail\JobFailedMail; §5.2.7, D-298). --}}
<x-mail::message>
# {{ $greeting }}

{{ $intro }}

<x-mail::table>
| | |
|:--|:--|
@foreach ($details as $label => $value)
| {{ $label }} | {{ $value }} |
@endforeach
</x-mail::table>

{{ $note }}

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>
</x-mail::message>
