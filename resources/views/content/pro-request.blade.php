<x-layouts.app title="Professionnels" description="Artisans, restaurateurs, traiteurs : demandez l'ouverture d'un compte professionnel pour commander nos produits 100 % sans gluten.">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => 'Professionnels']]" class="mb-6" />
        <h1 class="mb-6 text-2xl font-semibold text-ink">Espace professionnels</h1>

        {{-- HTML saisi en BO, purifié (liste blanche) par le contrôleur avant affichage. --}}
        <div class="prose prose-sm mb-10 max-w-none">{!! $introHtml !!}</div>

        <section aria-labelledby="pro-form-title" class="rounded-card border border-line bg-white p-6">
            <h2 id="pro-form-title" class="mb-4 text-lg font-semibold text-ink">Demande de compte professionnel</h2>

            @if (session('pro_sent'))
                <x-ui.alert variant="success" class="mb-4">Merci, votre demande a bien été envoyée. Un email de confirmation vient de vous être adressé et nous revenons vers vous dans les meilleurs délais.</x-ui.alert>
            @endif
            @if (session('pro_error'))
                <x-ui.alert variant="warning" class="mb-4">{{ session('pro_error') }}</x-ui.alert>
            @endif

            <p class="mb-4 text-sm text-ink-muted">Seuls les champs marqués d'un astérisque (*) sont obligatoires.</p>

            <form method="POST" action="{{ route('pro.request.store') }}" class="flex flex-col gap-4">
                @csrf

                <x-ui.field name="company_name" label="Raison sociale (facultatif)">
                    <x-ui.input name="company_name" autocomplete="organization" maxlength="255" />
                </x-ui.field>

                <x-ui.field name="siret" label="N° SIRET (facultatif)" hint="14 chiffres">
                    <x-ui.input name="siret" inputmode="numeric" maxlength="20" />
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="activity_type" label="Type d'activité (facultatif)">
                        <select name="activity_type" id="activity_type"
                            class="w-full rounded-field border bg-white px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-sage {{ $errors->has('activity_type') ? 'border-red-400' : 'border-line' }}">
                            <option value="">Choisir…</option>
                            @foreach ($activityTypes as $type)
                                <option value="{{ $type }}" @selected(old('activity_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field name="activity_other" label="Si « Autre », précisez">
                        <x-ui.input name="activity_other" maxlength="150" />
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="contact_last_name" label="Nom du contact *">
                        <x-ui.input name="contact_last_name" autocomplete="family-name" required maxlength="100" />
                    </x-ui.field>
                    <x-ui.field name="contact_first_name" label="Prénom du contact *">
                        <x-ui.input name="contact_first_name" autocomplete="given-name" required maxlength="100" />
                    </x-ui.field>
                </div>

                <x-ui.field name="job_title" label="Fonction (facultatif)">
                    <x-ui.input name="job_title" autocomplete="organization-title" maxlength="100" />
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="email" label="Email professionnel *">
                        <x-ui.input name="email" type="email" autocomplete="email" required maxlength="150" />
                    </x-ui.field>
                    <x-ui.field name="phone" label="Téléphone *">
                        <x-ui.input name="phone" type="tel" autocomplete="tel" required maxlength="30" />
                    </x-ui.field>
                </div>

                <x-ui.field name="address_line1" label="Adresse de l'établissement (facultatif)">
                    <x-ui.input name="address_line1" autocomplete="street-address" maxlength="255" />
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="postal_code" label="Code postal (facultatif)">
                        <x-ui.input name="postal_code" autocomplete="postal-code" inputmode="numeric" maxlength="5" />
                    </x-ui.field>
                    <x-ui.field name="city" label="Ville (facultatif)">
                        <x-ui.input name="city" autocomplete="address-level2" maxlength="100" />
                    </x-ui.field>
                </div>

                <x-ui.field name="description" label="Description de votre besoin (facultatif)" hint="2 000 caractères maximum">
                    <textarea name="description" id="description" rows="6" maxlength="2000"
                        class="w-full rounded-field border px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-sage {{ $errors->has('description') ? 'border-red-400' : 'border-line' }}">{{ old('description') }}</textarea>
                </x-ui.field>

                <x-ui.field name="consent">
                    <label class="flex items-start gap-2 text-sm text-ink">
                        <input type="checkbox" name="consent" id="consent" value="1" required
                            class="mt-0.5 rounded border-line text-sage focus:ring-sage" @checked(old('consent'))>
                        <span>
                            J'accepte que mes données soient traitées pour étudier ma demande de compte professionnel.
                            <a href="{{ route('content.show', ['slug' => 'politique-de-confidentialite']) }}" class="underline hover:text-sage">Politique de confidentialité</a>
                        </span>
                    </label>
                </x-ui.field>

                <x-ui.field name="cf-turnstile-response">
                    <x-ui.turnstile />
                </x-ui.field>

                <div><x-ui.button type="submit">Envoyer ma demande</x-ui.button></div>
            </form>
        </section>
    </div>
</x-layouts.app>
