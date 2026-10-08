@props(['url'])
@php($logoPath = app(\App\Settings\HomepageSettings::class)->logo_path)
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoPath)
<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}" class="logo" alt="{{ config('app.name') }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
