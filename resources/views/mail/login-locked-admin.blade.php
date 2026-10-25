{{-- Lock after 5 failed attempts, to the admins (App\Mail\LoginLockedAdminMail; M01 §7, D-274). --}}
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

{{ $unlock }}

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>
</x-mail::message>
