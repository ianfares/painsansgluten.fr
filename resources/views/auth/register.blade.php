<x-layouts.auth title="Créer un compte">
    <form method="POST" action="{{ route('register') }}">
        @csrf

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
