<x-layouts.app :title="$page?->seo_title ?: 'Contact'">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => 'Contact']]" class="mb-6" />
        <h1 class="mb-6 text-2xl font-semibold text-ink">{{ $page?->title ?: 'Contact' }}</h1>

        @if ($page)
            <div class="prose prose-sm mb-10 max-w-none">{!! $page->content !!}</div>
        @endif

        <section aria-labelledby="contact-form-title" class="rounded-card border border-line bg-white p-6">
            <h2 id="contact-form-title" class="mb-4 text-lg font-semibold text-ink">Écrivez-nous</h2>

            @if (session('contact_sent'))
                <x-ui.alert variant="success" class="mb-4">Merci, votre message a bien été envoyé. Nous vous répondrons dès que possible.</x-ui.alert>
            @endif
            @if (session('contact_error'))
                <x-ui.alert variant="warning" class="mb-4">{{ session('contact_error') }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('contact.send') }}" class="flex flex-col gap-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="name" label="Nom">
                        <x-ui.input name="name" autocomplete="name" required maxlength="100" />
                    </x-ui.field>
                    <x-ui.field name="email" label="Email">
                        <x-ui.input name="email" type="email" autocomplete="email" required maxlength="150" />
                    </x-ui.field>
                </div>
                <x-ui.field name="phone" label="Téléphone (facultatif)">
                    <x-ui.input name="phone" type="tel" autocomplete="tel" maxlength="30" />
                </x-ui.field>
                <x-ui.field name="message" label="Votre message">
                    <textarea name="message" id="message" rows="6" required minlength="10" maxlength="5000"
                        class="w-full rounded-field border px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-sage {{ $errors->has('message') ? 'border-red-400' : 'border-line' }}">{{ old('message') }}</textarea>
                </x-ui.field>

                <x-ui.field name="cf-turnstile-response">
                    <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-language="fr"></div>
                </x-ui.field>

                <p class="text-xs text-ink-muted">
                    Les informations saisies servent uniquement à répondre à votre message.
                    <a href="{{ url('/politique-de-confidentialite') }}" class="underline hover:text-sage">Politique de confidentialité</a>
                </p>

                <div><x-ui.button type="submit">Envoyer</x-ui.button></div>
            </form>
        </section>
    </div>

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</x-layouts.app>
