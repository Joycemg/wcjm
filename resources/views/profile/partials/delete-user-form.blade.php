{{-- resources/views/profile/_delete_account.blade.php --}}
<section class="card"
         style="max-width:720px;margin-inline:auto">
    <header>
        <h2 style="color:var(--maroon);margin:0">{{ __('Eliminar cuenta') }}</h2>
        <p class="muted"
           style="margin:.25rem 0 0">
            {{ __('Al eliminar tu cuenta, todos tus datos se borrarán de forma permanente. Descargá antes cualquier información que quieras conservar.') }}
        </p>
    </header>

    @php
        // Soporte para bolsa de errores nombrada (Breeze usa "userDeletion")
        /** @var \Illuminate\Support\ViewErrorBag $errors */
        $delErrors = method_exists($errors, 'getBag') ? $errors->getBag('userDeletion') : $errors;
        $openOnLoad = $delErrors && ($delErrors->any() || session('open_delete_modal'));
      @endphp

    {{-- Botón que abre el modal --}}
    <div style="margin-top:.75rem">
        <button id="btn-open-del"
                class="btn danger">{{ __('Eliminar cuenta') }}</button>
    </div>

    {{-- Fallback sin JS: bloque visible sólo si JS está desactivado --}}
    <div class="no-js-only"
         style="margin-top:.75rem">
        <details>
            <summary class="muted"
                     style="cursor:pointer">{{ __('Abrir confirmación (sin JavaScript)') }}</summary>
            <form method="POST"
                  action="{{ route('profile.destroy') }}"
                  style="margin-top:.6rem">
                @csrf @method('DELETE')
                <label for="password_nojs">{{ __('Contraseña') }}</label>
                <input id="password_nojs"
                       type="password"
                       name="password"
                       required
                       autocomplete="current-password"
                       style="margin:.25rem 0 .6rem;display:block;width:100%">
                @if($delErrors && $delErrors->has('password'))
                    <div class="text-danger"
                         style="margin:.25rem 0">{{ $delErrors->first('password') }}</div>
                @endif
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <button class="btn danger"
                            type="submit">{{ __('Eliminar cuenta') }}</button>
                    <a class="btn"
                       href="{{ url()->current() }}">{{ __('Cancelar') }}</a>
                </div>
            </form>
        </details>
    </div>

    {{-- Modal accesible (oculto por defecto) --}}
    <div id="modal-del"
         class="modal-backdrop"
         data-open="{{ $openOnLoad ? '1' : '0' }}"
         hidden>
        <div class="modal-dialog card"
             role="dialog"
             aria-modal="true"
             aria-labelledby="del-title"
             aria-describedby="del-desc">
            <form id="form-del"
                  method="POST"
                  action="{{ route('profile.destroy') }}">
                @csrf @method('DELETE')

                <h3 id="del-title"
                    style="color:var(--maroon);margin:.2rem 0">{{ __('¿Seguro que querés eliminar tu cuenta?') }}</h3>
                <p id="del-desc"
                   class="muted"
                   style="margin:.25rem 0 .75rem">
                    {{ __('Esta acción es permanente. Ingresá tu contraseña para confirmar.') }}
                </p>

                <label for="password">{{ __('Contraseña') }}</label>
                <input id="password"
                       name="password"
                       type="password"
                       required
                       autocomplete="current-password"
                       style="margin:.25rem 0 .25rem;display:block;width:100%">
                @if($delErrors && $delErrors->has('password'))
                    <div class="text-danger"
                         style="margin:.25rem 0">{{ $delErrors->first('password') }}</div>
                @endif

                <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.75rem">
                    <button type="button"
                            class="btn"
                            id="btn-cancel-del">{{ __('Cancelar') }}</button>
                    <button type="submit"
                            class="btn danger"
                            id="btn-confirm-del">{{ __('Eliminar cuenta') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>

@push('head')
    <style>
        /* Modal básico */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, .35);
            padding: 1rem;
            z-index: 50
        }

        .modal-dialog {
            max-width: 520px;
            width: 100%
        }

        .no-js-only {
            display: none
        }
    </style>
    <noscript>
        <style>
            .no-js-only {
                display: block
            }
        </style>
    </noscript>
@endpush

@push('scripts')
    @once
        <script>
            (function () {
                const qs = (s, r = document) => r.querySelector(s);
                const modal = qs('#modal-del');
                const openBtn = qs('#btn-open-del');
                const cancel = qs('#btn-cancel-del');
                const form = qs('#form-del');
                const confirm = qs('#btn-confirm-del');
                const pwd = qs('#password');

                let lastFocus = null;

                function openModal() {
                    if (!modal) return;
                    lastFocus = document.activeElement;
                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');
                    // focus al campo password
                    setTimeout(() => { pwd && pwd.focus(); }, 0);
                    trapFocus(true);
                }

                function closeModal() {
                    if (!modal) return;
                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                    trapFocus(false);
                    if (lastFocus && lastFocus.focus) lastFocus.focus();
                }

                // Trap de foco simple dentro del modal
                let trapHandler = null;
                function trapFocus(enable) {
                    if (!enable) {
                        document.removeEventListener('keydown', trapHandler);
                        trapHandler = null;
                        return;
                    }
                    const focusables = () => Array.from(modal.querySelectorAll(
                        'a[href],button:not([disabled]),input,textarea,select,[tabindex]:not([tabindex="-1"])'
                    )).filter(el => el.offsetParent !== null);
                    trapHandler = (e) => {
                        if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
                        if (e.key !== 'Tab') return;
                        const nodes = focusables(); if (!nodes.length) return;
                        const first = nodes[0], last = nodes[nodes.length - 1];
                        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                    };
                    document.addEventListener('keydown', trapHandler);
                }

                // Abrir/Cerrar
                openBtn?.addEventListener('click', (e) => { e.preventDefault(); openModal(); }, { passive: false });
                cancel?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); }, { passive: false });
                modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); }, { passive: true });

                // Abrir automáticamente si hay errores del bag (post back de validación)
                if (modal && modal.dataset.open === '1') { openModal(); }

                // Anti doble submit
                form?.addEventListener('submit', () => {
                    if (confirm) {
                        confirm.disabled = true;
                        confirm.setAttribute('aria-disabled', 'true');
                        confirm.textContent = @json(__('Eliminando…'));
                    }
                });
            })();
        </script>
    @endonce
@endpush