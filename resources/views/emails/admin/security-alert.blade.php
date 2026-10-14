@component('mail::message')
# ALERTE — {{ $topic }}

**Site :** {{ $siteName }} ({{ $siteUrl }})<br>
**Détecté le :** {{ $detectedAt }} (heure de Paris)

{{ $intro }}

@foreach ($details as $title => $lines)
**{{ $title }}**

@if (is_array($lines))
@foreach (array_slice($lines, 0, 200) as $line)
- `{{ $line }}`
@endforeach
@if (count($lines) > 200)
- … et {{ count($lines) - 200 }} autre(s)
@endif
@else
{{ $lines }}
@endif

@endforeach
---

{{ $advice }}
@endcomponent
