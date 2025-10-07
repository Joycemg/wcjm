{{-- resources/views/components/auth/register-modal.blade.php --}}
@props([])

@php
    use Illuminate\Support\Facades\Route;

    // Abrir si falló el registro o si llega ?register=1
    $shouldOpen = request()->boolean('register')
        || $errors->has('name')
        || $errors->has('email')
        || $errors->has('password')
        || $errors->has('password_confirmation');

    // Rutas (con fallback)
    $hasRegister = Route::has('register');
    $registerAction = $hasRegister ? route('register') : url('/register');
    $registerPath = parse_url($registerAction, PHP_URL_PATH) ?: '/register';

    $hasLogin = Route::has('login');
    $loginHref = $hasLogin ? route('login') : url('/login');
@endphp

<style>
    /* === Modal responsive (igual estilo que login) === */
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
        border-radius: 0;
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
        border-radius: 0;
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
        border-radius: 0;
        padding: .6rem .9rem;
        cursor: pointer
    }

    .lt-handle {
        display: none;
        width: 44px;
        height: 4px;
        background: var(--line);
        border-radius: 0;
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
        border-radius: 0;
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
            border-radius: 0;
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

<div id="register-modal"
     class="lt-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="register-modal-title"
     hidden>
    <!-- Overlay -->
    <button type="button"
            class="lt-modal__overlay"
            data-register-close
            aria-label="{{ __('Cerrar') }}"></button>

    <!-- Panel -->
    <div class="lt-modal__container">
        <div class="lt-modal__panel">
            <div class="lt-handle"
                 aria-hidden="true"></div>

            <div class="lt-modal__header">
                <h2 id="register-modal-title"
                    class="lt-modal__title">{{ __('Crear cuenta') }}</h2>
                <button type="button"
                        class="lt-modal__close"
                        data-register-close
                        aria-label="{{ __('Cerrar') }}">×</button>
            </div>

            <div class="lt-modal__body">
                {{-- Errores globales --}}
                @if ($errors->any())
                    <div class="flash-local"
                         role="alert">
                        <strong style="display:block;margin-bottom:.25rem">{{ __('Revisá los campos:') }}</strong>
                        <ul class="err-list">@foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach</ul>
                    </div>
                @endif

                {{-- Aviso si no hay ruta de registro --}}
                @unless($hasRegister)
                    <div class="flash-local"
                         role="alert">{{ __('El registro no está habilitado en este sitio.') }}</div>
                @endunless

                <form method="{{ $hasRegister ? 'POST' : 'GET' }}"
                      action="{{ $registerAction }}"
                      id="register-form"
                      novalidate
                      @unless($hasRegister)
                          onsubmit="return false"
                      @endunless>
                    @csrf

                    {{-- Nombre --}}
                    <div class="field">
                        <label for="reg_name">{{ __('Nombre') }}</label>
                        <input id="reg_name"
                               name="name"
                               type="text"
                               maxlength="120"
                               value="{{ old('name') }}"
                               required
                               autocomplete="name"
                               @error('name')
                                   aria-invalid="true"
                               @enderror>
                        <div class="help">{{ __('Cómo querés que te vean.') }}</div>
                        @error('name') <div class="error-inline">{{ $message }}</div> @enderror
                    </div>

                    {{-- Email --}}
                    <div class="field">
                        <label for="reg_email">{{ __('Email') }}</label>
                        <input id="reg_email"
                               name="email"
                               type="email"
                               maxlength="191"
                               value="{{ old('email') }}"
                               required
                               autocomplete="email"
                               inputmode="email"
                               autocapitalize="none"
                               autocorrect="off"
                               spellcheck="false"
                               @error('email')
                                   aria-invalid="true"
                               @enderror>
                        <div class="help">{{ __('Usá un email válido (te vamos a verificar).') }}</div>
                        @error('email') <div class="error-inline">{{ $message }}</div> @enderror
                    </div>

                    {{-- Password --}}
                    <div class="field">
                        <label for="reg_password">{{ __('Contraseña') }}</label>
                        <input id="reg_password"
                               name="password"
                               type="password"
                               minlength="8"
                               required
                               autocomplete="new-password"
                               @error('password')
                                   aria-invalid="true"
                               @enderror>
                        <div class="help">{{ __('Mínimo 8 caracteres.') }}</div>
                        @error('password') <div class="error-inline">{{ $message }}</div> @enderror
                    </div>

                    {{-- Confirmación --}}
                    <div class="field">
                        <label for="reg_password_confirmation">{{ __('Confirmar contraseña') }}</label>
                        <input id="reg_password_confirmation"
                               name="password_confirmation"
                               type="password"
                               minlength="8"
                               required
                               autocomplete="new-password"
                               @error('password_confirmation')
                                   aria-invalid="true"
                               @enderror>
                        @error('password_confirmation') <div class="error-inline">{{ $message }}</div> @enderror
                        <div id="reg_mismatch"
                             class="error-inline"
                             style="display:none">{{ __('Las contraseñas no coinciden.') }}</div>
                    </div>

                    <div class="lt-actions">
                        <div>
                            <a class="lt-link"
                               href="{{ $loginHref }}"
                               data-login-open>
                                {{ __('¿Ya tenés cuenta? Iniciá sesión') }}
                            </a>
                        </div>
                        <div style="display:flex; gap:.5rem; align-items:center; width:100%; justify-content:flex-end;">
                            <button type="button"
                                    class="lt-cancel"
                                    data-register-close>{{ __('Cancelar') }}</button>
                            <button id="register-submit"
                                    class="btn ok"
                                    type="submit"
                                    @unless($hasRegister)
                                        disabled
                                        aria-disabled="true"
                                    @endunless>
                                {{ __('Crear cuenta') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div> <!--/body-->
        </div>
    </div>
</div>

@push('scripts')
    @once
        <script>
            (function () {
                const modal = document.getElementById('register-modal');
                const panel = modal?.querySelector('.lt-modal__panel');
                const form = document.getElementById('register-form');
                const submit = document.getElementById('register-submit');
                const nameEl = document.getElementById('reg_name');
                const emailEl = document.getElementById('reg_email');
                const passEl = document.getElementById('reg_password');
                const confEl = document.getElementById('reg_password_confirmation');
                const mismatch = document.getElementById('reg_mismatch');
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
                    const focusTarget = nameEl || panel.querySelector(focusableSel);
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

                // Abrir con [data-register-open]
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-register-open]');
                    if (btn) { e.preventDefault(); open(btn); }
                });

                // Cerrar con overlay o botones
                modal.querySelectorAll('[data-register-close]').forEach(el => {
                    el.addEventListener('click', (e) => { e.preventDefault(); close(); });
                });

                // Interceptar enlaces a /register para abrir modal (sin route() en JS)
                const registerPath = @json($registerPath);
                document.addEventListener('click', (e) => {
                    const a = e.target.closest('a[href]'); if (!a) return;
                    try {
                        const url = new URL(a.href, window.location.origin);
                        if (url.pathname === registerPath) { e.preventDefault(); open(a); }
                    } catch (_) { }
                });

                // Link "¿Ya tenés cuenta?" → cierra y abre login
                document.addEventListener('click', (e) => {
                    const a = e.target.closest('[data-login-open]');
                    if (!a) return;
                    e.preventDefault();
                    close();
                    document.dispatchEvent(new CustomEvent('auth:open', { detail: { type: 'login' } }));
                });

                // Hook externo
                document.addEventListener('auth:open', (ev) => {
                    if (ev?.detail?.type === 'register') open();
                });

                // Anti doble submit + validaciones rápidas
                if (form && submit) {
                    const validateMatch = () => {
                        const ok = (passEl?.value || '') === (confEl?.value || '');
                        if (mismatch) mismatch.style.display = ok ? 'none' : 'block';
                        return ok;
                    };
                    confEl?.addEventListener('input', validateMatch, { passive: true });
                    passEl?.addEventListener('input', validateMatch, { passive: true });
                    form.addEventListener('submit', (ev) => {
                        if (emailEl) emailEl.value = (emailEl.value || '').trim();
                        if (!validateMatch()) { ev.preventDefault(); confEl?.focus(); return; }
                        submit.disabled = true;
                        submit.setAttribute('aria-disabled', 'true');
                        submit.textContent = {{ Js::from(__('Creando…')) }};
                    });
                }

                        // Auto-abrir si hubo error o ?register=1
                        @if($shouldOpen) open(); @endif
                      })();
        </script>
    @endonce
@endpush