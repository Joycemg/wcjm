{{-- resources/views/components/auth/login-modal.blade.php --}}
@props([])

@php
    use Illuminate\Support\Facades\Route;

    // Abrir automáticamente si falló el login, hay status o llega ?login=1
    $shouldOpen = ($errors->has('email') || $errors->has('password')) || session('status') || request()->boolean('login');

    // Rutas (con fallback seguro para entornos sin auth instalado)
    $hasLogin = Route::has('login');
    $loginAction = $hasLogin ? route('login') : url('/login');
    $loginPath = parse_url($loginAction, PHP_URL_PATH) ?: '/login';

    $hasForgot = Route::has('password.request');
    $forgotHref = $hasForgot ? route('password.request') : url('/password/reset');
@endphp

<style>
    /* === Modal responsive sin Tailwind === */
    :root {
        --sheet-gutter: 16px
    }

    .lt-modal[hidden] {
        display: none
    }

    .lt-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        height: 100vh;
        height: 100dvh;
        overscroll-behavior: contain
    }

    .lt-modal__overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .5);
        border: 0;
        padding: 0;
        margin: 0
    }

    .lt-modal__container {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem var(--sheet-gutter)
    }

    .lt-modal__panel {
        width: min(100%, 420px);
        max-height: calc(100dvh - 2*var(--sheet-gutter));
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 1rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, .18);
        overflow: auto;
        -webkit-overflow-scrolling: touch
    }

    .lt-modal__header {
        position: relative;
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--line)
    }

    .lt-modal__title {
        margin: 0;
        font-size: 1.05rem;
        color: var(--ink)
    }

    .lt-modal__close {
        position: absolute;
        right: .4rem;
        top: .4rem;
        width: 40px;
        height: 40px;
        border-radius: 999px;
        border: 0;
        background: transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        line-height: 1
    }

    .lt-modal__body {
        padding: 1rem;
        padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px))
    }

    .field+.field {
        margin-top: .9rem
    }

    .help {
        font-size: .9rem;
        color: var(--muted)
    }

    .err-list {
        margin: .5rem 0 0 1rem
    }

    .lt-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: 1rem
    }

    .lt-link {
        font-size: .9rem;
        color: var(--maroon);
        text-decoration: underline
    }

    .lt-cancel {
        background: transparent;
        border: 1px solid var(--line);
        border-radius: .6rem;
        padding: .6rem .9rem;
        cursor: pointer
    }

    .lt-handle {
        display: none;
        width: 44px;
        height: 4px;
        background: var(--line);
        border-radius: 999px;
        margin: .35rem auto 0
    }

    .error-inline {
        color: #7f1d1d;
        font-size: .9rem;
        margin-top: .25rem
    }

    .flash-local {
        margin-bottom: .75rem;
        padding: .6rem;
        border: 1px solid #F3B9B9;
        background: #FCECEC;
        border-radius: .6rem;
        color: #7f1d1d;
        font-size: .9rem
    }

    @media (max-width:600px) {
        .lt-modal__container {
            align-items: flex-end;
            padding: 0;
            padding-top: env(safe-area-inset-top, 0px)
        }

        .lt-modal__panel {
            width: 100%;
            max-width: none;
            border-radius: 1rem 1rem 0 0;
            max-height: calc(100dvh - 10px - env(safe-area-inset-bottom, 0px));
            transform: translateY(8%);
            animation: lt-slide-up .18s ease-out forwards
        }

        .lt-modal__header {
            padding: .7rem 1rem .6rem
        }

        .lt-handle {
            display: block
        }

        .lt-actions {
            flex-direction: column;
            align-items: stretch
        }

        .lt-actions>div:first-child {
            align-self: flex-start
        }
    }

    @keyframes lt-slide-up {
        to {
            transform: translateY(0)
        }
    }
</style>

<div id="login-modal"
     class="lt-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="login-modal-title"
     hidden>
    <!-- Overlay -->
    <button type="button"
            class="lt-modal__overlay"
            data-login-close
            aria-label="{{ __('Cerrar') }}"></button>

    <!-- Panel -->
    <div class="lt-modal__container"
         data-login-container>
        <div class="lt-modal__panel">
            <div class="lt-handle"
                 aria-hidden="true"></div>

            <div class="lt-modal__header">
                <h2 id="login-modal-title"
                    class="lt-modal__title">{{ __('Iniciar sesión') }}</h2>
                <button type="button"
                        class="lt-modal__close"
                        data-login-close
                        aria-label="{{ __('Cerrar') }}">×</button>
            </div>

            <div class="lt-modal__body">
                {{-- Status --}}
                @if (session('status'))
                    <div class="flash"
                         role="status"
                         aria-live="polite">{{ session('status') }}</div>
                @endif

                {{-- Errores globales --}}
                @if ($errors->any())
                    <div class="flash-local"
                         role="alert">
                        <strong style="display:block;margin-bottom:.25rem">{{ __('Revisá los campos:') }}</strong>
                        <ul class="err-list">@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul>
                    </div>
                @endif

                {{-- Aviso si no hay ruta de login --}}
                @unless($hasLogin)
                    <div class="flash-local"
                         role="alert">{{ __('El inicio de sesión no está habilitado en este sitio.') }}</div>
                @endunless

                <form method="{{ $hasLogin ? 'POST' : 'GET' }}"
                      action="{{ $loginAction }}"
                      id="login-form"
                      novalidate
                      @unless($hasLogin)
                          onsubmit="return false"
                      @endunless>
                    @csrf

                    {{-- Email --}}
                    <div class="field">
                        <label for="login_email">{{ __('Email') }}</label>
                        <input id="login_email"
                               name="email"
                               type="email"
                               maxlength="191"
                               value="{{ old('email') }}"
                               required
                               autocomplete="username"
                               inputmode="email"
                               autocapitalize="none"
                               autocorrect="off"
                               spellcheck="false"
                               aria-describedby="login_email_help"
                               @error('email')
                                   aria-invalid="true"
                               @enderror>
                        <div id="login_email_help"
                             class="help">{{ __('Usá tu email de registro.') }}</div>
                        @error('email') <div class="error-inline">{{ $message }}</div> @enderror
                    </div>

                    {{-- Password --}}
                    <div class="field">
                        <label for="login_password">{{ __('Contraseña') }}</label>
                        <input id="login_password"
                               name="password"
                               type="password"
                               required
                               autocomplete="current-password"
                               @error('password')
                                   aria-invalid="true"
                               @enderror>
                        @error('password') <div class="error-inline">{{ $message }}</div> @enderror
                    </div>

                    {{-- Remember --}}
                    <div class="field"
                         style="display:flex;align-items:center;gap:.5rem">
                        <input id="login_remember"
                               type="checkbox"
                               name="remember"
                               {{ old('remember') ? 'checked' : '' }}>
                        <label for="login_remember"
                               style="margin:0">{{ __('Recordarme') }}</label>
                    </div>

                    {{-- Acciones --}}
                    <div class="lt-actions">
                        <div>
                            <a class="lt-link"
                               href="{{ $forgotHref }}">{{ __('¿Olvidaste tu contraseña?') }}</a>
                        </div>
                        <div style="display:flex;gap:.5rem;align-items:center;width:100%;justify-content:flex-end">
                            <button type="button"
                                    class="lt-cancel"
                                    data-login-close>{{ __('Cancelar') }}</button>
                            <button id="login-submit"
                                    class="btn ok"
                                    type="submit"
                                    @unless($hasLogin)
                                        disabled
                                        aria-disabled="true"
                                    @endunless>
                                {{ __('Ingresar') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @once
        <script>
            (function () {
                const modal = document.getElementById('login-modal');
                const panel = modal?.querySelector('.lt-modal__panel');
                const form = document.getElementById('login-form');
                const submit = document.getElementById('login-submit');
                const email = document.getElementById('login_email');
                const pwd = document.getElementById('login_password');
                if (!modal || !panel) return;

                let lastTrigger = null;
                const focusableSel = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

                function lockScroll() { document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden' }
                function unlockScroll() { document.documentElement.style.overflow = ''; document.body.style.overflow = '' }
                function trapTab(e) {
                    if (e.key !== 'Tab') return;
                    const nodes = [...panel.querySelectorAll(focusableSel)].filter(el => el.offsetParent !== null);
                    if (!nodes.length) return;
                    const first = nodes[0], last = nodes[nodes.length - 1];
                    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
                    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
                }
                function open(triggerEl) {
                    lastTrigger = triggerEl || document.activeElement;
                    modal.hidden = false; lockScroll();
                    const focusTarget = (email?.value ? pwd : email) || panel.querySelector(focusableSel);
                    setTimeout(() => focusTarget?.focus(), 0);
                    document.addEventListener('keydown', onKeydown);
                    document.addEventListener('keydown', trapTab);
                    modal.addEventListener('touchmove', preventBgScroll, { passive: false });
                }
                function close() {
                    modal.hidden = true; unlockScroll();
                    document.removeEventListener('keydown', onKeydown);
                    document.removeEventListener('keydown', trapTab);
                    modal.removeEventListener('touchmove', preventBgScroll);
                    lastTrigger?.focus?.();
                }
                function onKeydown(e) { if (e.key === 'Escape') { e.preventDefault(); close() } }
                function preventBgScroll(e) { if (!panel.contains(e.target)) e.preventDefault() }

                // Abrir con cualquier [data-login-open]
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-login-open]');
                    if (btn) { e.preventDefault(); open(btn); }
                });

                // Cerrar con overlay o botones marcados
                modal.querySelectorAll('[data-login-close]').forEach(el => {
                    el.addEventListener('click', (e) => { e.preventDefault(); close(); });
                });

                // Interceptar enlaces a /login para abrir modal (sin usar route() en JS)
                const loginPath = @json($loginPath);
                document.addEventListener('click', (e) => {
                    const a = e.target.closest('a[href]'); if (!a) return;
                    try {
                        const url = new URL(a.href, window.location.origin);
                        if (url.pathname === loginPath) { e.preventDefault(); open(a); }
                    } catch (_) { }
                });

                // Hook externo
                document.addEventListener('auth:open', (ev) => {
                    if (ev?.detail?.type === 'login') open();
                });

                // Anti doble submit + trim de email
                if (form && submit) {
                    form.addEventListener('submit', () => {
                        if (email) email.value = (email.value || '').trim();
                        submit.disabled = true;
                        submit.setAttribute('aria-disabled', 'true');
                        submit.textContent = {{ Js::from(__('Ingresando…')) }};
                    });
                }

                        // Auto-abrir si hubo error o ?login=1
                        @if($shouldOpen) open(); @endif
                      })();
        </script>
    @endonce
@endpush