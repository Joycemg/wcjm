<x-guest-layout>
    <header class="auth-header">
        <h1 class="auth-title">{{ __('Crear cuenta') }}</h1>
        <p class="form-hint">{{ __('Unite a la comunidad y empezá a organizar tus mesas.') }}</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="form-alert form-alert-error">
            <strong class="form-alert-title">{{ __('Revisá los campos:') }}</strong>
            <ul>
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="form-grid" novalidate>
        @csrf

        <div class="form-field">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="form-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="form-field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="form-field">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="form-actions">
            <div class="form-links">
                <a class="form-link" href="{{ route('login') }}">{{ __('Already registered?') }}</a>
            </div>
            <div class="form-actions-end">
                <x-primary-button>
                    {{ __('Register') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>
