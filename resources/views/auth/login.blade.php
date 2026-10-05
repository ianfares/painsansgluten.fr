<x-layouts.auth title="Connexion">
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required>
        @error('password') <p class="error">{{ $message }}</p> @enderror

        <label><input type="checkbox" name="remember" style="width:auto;display:inline"> Se souvenir de moi</label>

        <button type="submit">Se connecter</button>
    </form>

    <p><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
    <p><a href="{{ route('register') }}">Créer un compte</a></p>
</x-layouts.auth>
