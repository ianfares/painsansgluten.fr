{{-- Événement GA4 au chargement de la page (T22). Rien hors production / sans GTM. --}}
@props(['name', 'data' => null])
@if ($data !== null && app(\App\Services\Analytics\Ecommerce::class)->enabled())
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push({!! json_encode(['event' => $name, 'ecommerce' => $data], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!});
    </script>
@endif
