{{-- E-mails of the proposals (App\Mail\ProposalMail; M01 §7, M07 F-08, D-474…D-478): no amount, no attachment. --}}
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

@if ($note)
{{ $note }}
@endif

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>
</x-mail::message>
