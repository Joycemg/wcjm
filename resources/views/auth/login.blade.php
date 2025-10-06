{{-- resources/views/auth/login.blade.php --}}
<x-guest-layout>
    <header class="mb-4">
        <h1 class="text-xl font-semibold text-gray-900">{{ __('Iniciar sesión') }}</h1>
    </header>

    {{-- Estado y errores globales --}}
    <x-auth-session-status class="mb-4"
                           :status="session('status')" />

    @if ($errors->any())
        <div role="alert"
             class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <strong class="block mb-1">{{ __('Revisá los campos:') }}</strong>
            <ul class="list-disc ps-5">
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
          class="space-y-4">
        @csrf

        {{-- Email --}}
        <div>
            <x-input-label for="email"
                           :value="__('Email')" />
            <x-text-input id="email"
                          class="block mt-1 w-full"
                          type="email"
                          name="email"
                          :value="old('email')"
                          required
                          autofocus
                          inputmode="email"
                          autocomplete="username"
                          spellcheck="false"
                          autocapitalize="none" />
            <x-input-error :messages="$errors->get('email')"
                           class="mt-2" />
        </div>

        {{-- Password + toggle + CapsLock --}}
        <div class="mt-4">
            <x-input-label for="password"
                           :value="__('Password')" />

            <div class="relative">
                <x-text-input id="password"
                              class="block mt-1 w-full pr-10"
                              type="password"
                              name="password"
                              required
                              autocomplete="current-password"
                              aria-describedby="caps_hint" />
                <button type="button"
                        id="toggle-pass"
                        class="absolute inset-y-0 right-0 mr-2 inline-flex items-center text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded px-1"
                        aria-label="{{ __('Mostrar/ocultar contraseña') }}">
                    👁
                </button>
            </div>

            <p id="caps_hint"
               class="mt-1 text-xs text-amber-600 hidden">
                {{ __('Aviso: Bloq Mayús está activado') }}
            </p>

            <x-input-error :messages="$errors->get('password')"
                           class="mt-2" />
        </div>

        {{-- Remember me --}}
        <div class="block mt-4">
            <label for="remember_me"
                   class="inline-flex items-center">
                <input id="remember_me"
                       type="checkbox"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                       name="remember"
                       {{ old('remember') ? 'checked' : '' }}>
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between mt-4">
            <div>
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                       href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif>
            </div>

            <div class="flex items-center gap-3">
                @if (Route::has('register'))
                    <a class="text-sm text-gray-600 hover:text-gray-900 underline"
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
                            caps.classList.toggle('hidden', !on);
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