<x-guest-layout>
    <header class="auth-header">
        <h1 class="auth-title">{{ __('¿Olvidaste tu contraseña?') }}</h1>
        <p class="form-hint">{{ __('Dejanos tu email y te enviaremos un enlace para restablecerla.') }}</p>
    </header>

    <x-auth-session-status class="form-alert form-alert-info" :status="session('status')" />

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

    <form method="POST" action="{{ route('password.email') }}" class="form-grid" novalidate>
        @csrf

        <div class="form-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="form-actions" style="justify-content:flex-end">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
