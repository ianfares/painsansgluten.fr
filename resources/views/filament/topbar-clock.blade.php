{{-- Date et heure (Europe/Paris), mises à jour chaque minute côté navigateur. --}}
@php($now = now('Europe/Paris'))
<div
    x-data="{
        label: @js($now->translatedFormat('l j F Y · H:i')),
        tick() {
            const parts = new Intl.DateTimeFormat('fr-FR', { timeZone: 'Europe/Paris', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }).formatToParts(new Date());
            const get = (type) => parts.find((p) => p.type === type)?.value ?? '';
            this.label = `${get('weekday')} ${get('day')} ${get('month')} ${get('year')} · ${get('hour')}:${get('minute')}`;
        },
    }"
    x-init="tick(); setInterval(() => tick(), 30000)"
    class="hidden whitespace-nowrap text-sm font-medium text-gray-600 sm:block dark:text-gray-300"
>
    <span x-text="label">{{ $now->translatedFormat('l j F Y · H:i') }}</span>
</div>
