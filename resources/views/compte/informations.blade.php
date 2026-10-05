@php($user = auth()->user())

<x-compte.layout title="Mes informations" active="informations">
    <h1 class="mb-6 text-2xl font-semibold text-ink">Mes informations</h1>

    <div class="flex flex-col gap-8">
        {{-- Profil (Fortify, T03) --}}
        <section class="rounded-card border border-line bg-white p-6">
            <h2 class="mb-4 font-semibold text-ink">Informations personnelles</h2>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="first_name" label="Prénom">
                        <x-ui.input name="first_name" :value="old('first_name', $user->first_name)" />
                    </x-ui.field>
                    <x-ui.field name="last_name" label="Nom">
                        <x-ui.input name="last_name" :value="old('last_name', $user->last_name)" />
                    </x-ui.field>
                    <x-ui.field name="phone" label="Téléphone">
                        <x-ui.input name="phone" :value="old('phone', $user->phone)" />
                    </x-ui.field>
                    <x-ui.field name="email" label="Email (changement = revérification)">
                        <x-ui.input name="email" type="email" :value="old('email', $user->email)" />
                    </x-ui.field>
                </div>
                <div><x-ui.button type="submit">Enregistrer</x-ui.button></div>
            </form>
        </section>

        {{-- Mot de passe (Fortify, T03) --}}
        <section class="rounded-card border border-line bg-white p-6">
            <h2 class="mb-4 font-semibold text-ink">Mot de passe</h2>
            <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field name="current_password" label="Mot de passe actuel">
                        <x-ui.input name="current_password" type="password" />
                    </x-ui.field>
                    <x-ui.field name="password" label="Nouveau mot de passe">
                        <x-ui.input name="password" type="password" />
                    </x-ui.field>
                    <x-ui.field name="password_confirmation" label="Confirmer">
                        <x-ui.input name="password_confirmation" type="password" />
                    </x-ui.field>
                </div>
                <div><x-ui.button type="submit">Changer le mot de passe</x-ui.button></div>
            </form>
        </section>

        {{-- Adresse de facturation --}}
        <section class="rounded-card border border-line bg-white p-6">
            <h2 class="mb-4 font-semibold text-ink">Adresse de facturation</h2>
            <form method="POST" action="{{ route('compte.address.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="first_name" label="Prénom">
                        <x-ui.input name="first_name" :value="old('first_name', $address?->first_name)" />
                    </x-ui.field>
                    <x-ui.field name="last_name" label="Nom">
                        <x-ui.input name="last_name" :value="old('last_name', $address?->last_name)" />
                    </x-ui.field>
                    <x-ui.field name="line1" label="Adresse" class="sm:col-span-2">
                        <x-ui.input name="line1" :value="old('line1', $address?->line1)" />
                    </x-ui.field>
                    <x-ui.field name="postal_code" label="Code postal">
                        <x-ui.input name="postal_code" :value="old('postal_code', $address?->postal_code)" />
                    </x-ui.field>
                    <x-ui.field name="city" label="Ville">
                        <x-ui.input name="city" :value="old('city', $address?->city)" />
                    </x-ui.field>
                </div>
                <div><x-ui.button type="submit">Enregistrer l'adresse</x-ui.button></div>
            </form>
        </section>

        {{-- Zone sensible --}}
        <section class="rounded-card border border-red-200 bg-red-50 p-6">
            <h2 class="mb-2 font-semibold text-red-700">Supprimer mon compte</h2>
            @if ($user->deletion_requested_at)
                <p class="text-sm text-red-700">Demande envoyée le {{ $user->deletion_requested_at->format('d/m/Y') }}, en cours de traitement.</p>
            @else
                <p class="mb-4 text-sm text-red-700">Cette demande sera traitée manuellement par notre équipe (vos commandes et factures sont conservées pour obligation comptable).</p>
                <form method="POST" action="{{ route('compte.deletion.request') }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline" class="border-red-300 text-red-700 hover:bg-red-100">
                        Demander la suppression de mon compte
                    </x-ui.button>
                </form>
            @endif
        </section>
    </div>
</x-compte.layout>
