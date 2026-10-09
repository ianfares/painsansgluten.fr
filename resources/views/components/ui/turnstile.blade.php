{{-- Widget Cloudflare Turnstile (anti-robot). La vérification réelle est faite côté serveur (App\Rules\Turnstile). --}}
<div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-language="fr"></div>
@once
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endonce
