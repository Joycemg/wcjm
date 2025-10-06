<x-guest-layout>
    <header class="auth-header">
        <h1 class="auth-title">{{ __('Confirmá tu contraseña') }}</h1>
        <p class="form-hint">{{ __('Por seguridad necesitamos que ingreses tu contraseña antes de continuar.') }}</p>
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

    <form method="POST" action="{{ route('password.confirm') }}" class="form-grid" novalidate>
        @csrf

        <div class="form-field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="form-actions" style="justify-content:flex-end">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
