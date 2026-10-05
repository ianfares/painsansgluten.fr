<x-layouts.auth title="Réinitialiser le mot de passe">
    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Nouveau mot de passe</label>
        <input id="password" type="password" name="password" required>
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <button type="submit">Réinitialiser le mot de passe</button>
    </form>
</x-layouts.auth>
