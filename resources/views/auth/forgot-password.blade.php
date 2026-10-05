<x-layouts.auth title="Mot de passe oublié">
    <p>Indiquez votre email, vous recevrez un lien de réinitialisation.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <button type="submit">Envoyer le lien de réinitialisation</button>
    </form>
</x-layouts.auth>
