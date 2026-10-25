@props(['url'])
{{-- Infront header (PR-08): text placeholder of the logo until the official one arrives; never a
     remote image (M01 §7). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span class="logo-text">{{ __('mail.logo') }}</span>
<span class="logo-subtitle">{{ __('mail.portal') }}</span>
</a>
</td>
</tr>
