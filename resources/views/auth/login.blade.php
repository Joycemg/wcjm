{{-- resources/views/auth/login.blade.php --}}
<x-guest-layout>
    <header class="auth-header">
        <h1 class="auth-title">{{ __('Iniciar sesión') }}</h1>
    </header>

    {{-- Estado y errores globales --}}
    <x-auth-session-status class="form-alert form-alert-info"
                           :status="session('status')" />

    @if ($errors->any())
        <div role="alert"
             class="form-alert form-alert-error">
            <strong class="form-alert-title">{{ __('Revisá los campos:') }}</strong>
            <ul>
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('login') }}"
          id="login-form"
          novalidate
          class="form-grid">
        @csrf

        {{-- Email --}}
        <div class="form-field">
            <x-input-label for="email"
                           :value="__('Email')" />
            <x-text-input id="email"
                          type="email"
                          name="email"
                          :value="old('email')"
                          required
                          autofocus
                          inputmode="email"
                          autocomplete="username"
                          spellcheck="false"
                          autocapitalize="none" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        {{-- Password + toggle + CapsLock --}}
        <div class="form-field">
            <x-input-label for="password"
                           :value="__('Password')" />

            <div class="password-wrap">
                <x-text-input id="password"
                              type="password"
                              name="password"
                              required
                              autocomplete="current-password"
                              aria-describedby="caps_hint" />
                <button type="button"
                        id="toggle-pass"
                        class="pass-toggle"
                        aria-label="{{ __('Mostrar/ocultar contraseña') }}">
                    👁
                </button>
            </div>

            <p id="caps_hint"
               class="form-hint form-hint-warning is-hidden">
                {{ __('Aviso: Bloq Mayús está activado') }}
            </p>

            <x-input-error :messages="$errors->get('password')" />
        </div>

        {{-- Remember me --}}
        <div class="form-check">
            <label for="remember_me">
                <input id="remember_me"
                       type="checkbox"
                       name="remember"
                       {{ old('remember') ? 'checked' : '' }}>
                <span>{{ __('Recordarme') }}</span>
            </label>
        </div>

        {{-- Actions --}}
        <div class="form-actions">
            <div class="form-links">
                @if (Route::has('password.request'))
                    <a class="form-link"
                       href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <div class="form-actions-end">
                @if (Route::has('register'))
                    <a class="form-link"
                       href="{{ route('register') }}">
                        {{ __('Crear cuenta') }}
                    </a>
                @endif
                <x-primary-button id="login-submit">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </div>
    </form>

    @push('scripts')
        @once
            <script>
                (function () {
                    const form = document.getElementById('login-form');
                    const submit = document.getElementById('login-submit');
                    const pass = document.getElementById('password');
                    const toggle = document.getElementById('toggle-pass');
                    const caps = document.getElementById('caps_hint');

                    // Toggle mostrar/ocultar contraseña
                    if (toggle && pass) {
                        toggle.addEventListener('click', () => {
                            const isText = pass.type === 'text';
                            pass.type = isText ? 'password' : 'text';
                            toggle.setAttribute('aria-pressed', String(!isText));
                        }, { passive: true });
                    }

                    // Aviso CapsLock
                    if (pass && caps) {
                        const onKey = (e) => {
                            const on = e.getModifierState && e.getModifierState('CapsLock');
                            caps.classList.toggle('is-hidden', !on);
                        };
                        pass.addEventListener('keydown', onKey, { passive: true });
                        pass.addEventListener('keyup', onKey, { passive: true });
                    }

                    // Evitar doble submit
                    if (form && submit) {
                        form.addEventListener('submit', () => {
                            submit.disabled = true;
                            submit.setAttribute('aria-disabled', 'true');
                            submit.textContent = '{{ __('Ingresando…') }}';
                        });
                    }
                })();
            </script>
        @endonce
    @endpush
</x-guest-layout>