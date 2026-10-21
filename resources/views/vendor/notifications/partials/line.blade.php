@php
    $text = trim((string) $line);
    $isCode = preg_match('/^\d{6}$/', $text) === 1;
@endphp
@if ($isCode)
<table class="otp" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="otp-cell" align="center">{{ $text }}</td>
</tr>
</table>
@else
{{ $line }}

@endif
