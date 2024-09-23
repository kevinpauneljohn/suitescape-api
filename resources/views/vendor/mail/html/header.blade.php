@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === config('app.name'))
<img src={{ asset('suitescape-logo.png') }} class="logo" alt="{{ config('app.name') }} Logo" onerror="this.style.display='none'; document.getElementById('fallback').style.display='inline';">
<span id="fallback" style="display:none;">{{ $slot }}</span>
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
