<x-layouts.auth title="Créer un compte">
    <form method="POST" action="{{ route('register') }}" x-data="{ type: @js(old('account_type', 'individual')) }">
        @csrf

        <fieldset>
            <legend>Je suis</legend>
            <label><input type="radio" name="account_type" value="individual" x-model="type"> Particulier</label>
            <label><input type="radio" name="account_type" value="pro" x-model="type"> Professionnel</label>
        </fieldset>
        @error('account_type') <p class="error">{{ $message }}</p> @enderror

        <div x-show="type === 'pro'" @if (old('account_type') !== 'pro') style="display: none" @endif>
            <label for="company_name">Raison sociale</label>
            <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" x-bind:required="type === 'pro'">
            @error('company_name') <p class="error">{{ $message }}</p> @enderror

            <label for="siret">SIRET</label>
            <input id="siret" type="text" name="siret" value="{{ old('siret') }}" inputmode="numeric" x-bind:required="type === 'pro'">
            @error('siret') <p class="error">{{ $message }}</p> @enderror

            <p>Votre compte professionnel sera validé par notre équipe. D'ici là, vous commandez comme un particulier.</p>
        </div>

        <label for="first_name">Prénom</label>
        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus>
        @error('first_name') <p class="error">{{ $message }}</p> @enderror

        <label for="last_name">Nom</label>
        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required>
        @error('last_name') <p class="error">{{ $message }}</p> @enderror

        <label for="phone">Téléphone mobile</label>
        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required>
        @error('phone') <p class="error">{{ $message }}</p> @enderror

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', request()->query('email', '')) }}" required>
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required>
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <x-ui.turnstile />
        @error('cf-turnstile-response') <p class="error">{{ $message }}</p> @enderror

        <button type="submit">Créer mon compte</button>
    </form>

    <p><a href="{{ route('login') }}">J'ai déjà un compte</a></p>
</x-layouts.auth>
