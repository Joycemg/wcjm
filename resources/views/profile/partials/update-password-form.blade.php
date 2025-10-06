{{-- resources/views/profile/_profile_info.blade.php (o donde lo estés usando) --}}
<section class="card"
         style="max-width:720px;margin-inline:auto">
    <header>
        <h2 style="color:var(--maroon);margin:0">{{ __('Información de perfil') }}</h2>
        <p class="muted"
           style="margin:.25rem 0 0">
            {{ __("Actualizá los datos de tu cuenta y tu email.") }}
        </p>
    </header>

    {{-- Form “oculto” para re-enviar verificación de email --}}
    <form id="send-verification"
          method="POST"
          action="{{ route('verification.send') }}"
          style="display:none">
        @csrf
    </form>

    {{-- Errores de validación locales (opcional si ya los mostrás globalmente en el layout) --}}
    @if ($errors->has('name') || $errors->has('email'))
        <div role="alert"
             style="margin:.75rem 0;padding:.75rem;border:1px solid #f87171;border-radius:.5rem;background:#fff5f5;color:#7f1d1d">
            <strong style="display:block;margin-bottom:.25rem">{{ __('Revisá los campos:') }}</strong>
            <ul style="margin:0;padding-left:1rem">
                @foreach ($errors->get('name') as $err) <li>{{ $err }}</li> @endforeach
                @foreach ($errors->get('email') as $err) <li>{{ $err }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form id="profile-info-form"
          method="POST"
          action="{{ route('profile.update') }}"
          class="grid"
          novalidate>
        @csrf
        @method('PATCH')

        {{-- Nombre --}}
        <div>
            <label for="name">{{ __('Nombre') }}</label>
            <input id="name"
                   name="name"
                   type="text"
                   required
                   maxlength="120"
                   value="{{ old('name', $user->name) }}"
                   autocomplete="name"
                   aria-describedby="name_help name_count"
                   @error('name')
                       aria-invalid="true"
                   @enderror>
            <small id="name_help"
                   class="muted">{{ __('Máximo :n caracteres.', ['n' => 120]) }}</small>
            <div id="name_count"
                 class="muted"
                 style="font-size:.8rem"
                 aria-live="polite"></div>
            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email">{{ __('Email') }}</label>
            <input id="email"
                   name="email"
                   type="email"
                   required
                   maxlength="191"
                   value="{{ old('email', $user->email) }}"
                   autocomplete="username"
                   aria-describedby="email_help email_status"
                   @error('email')
                       aria-invalid="true"
                   @enderror>
            <small id="email_help"
                   class="muted">{{ __('Usá un correo al que tengas acceso.') }}</small>
            @error('email') <div class="text-danger">{{ $message }}</div> @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                <div id="email_status"
                     class="muted"
                     style="margin-top:.35rem">
                    {{ __('Tu email no está verificado.') }}
                    <button type="submit"
                            form="send-verification"
                            class="btn"
                            style="padding:.25rem .6rem;min-height:auto">
                        {{ __('Reenviar correo de verificación') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="muted"
                           aria-live="polite"
                           style="margin:.35rem 0 0;color:#065f46">
                            {{ __('Enviamos un nuevo enlace de verificación a tu correo.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Acciones --}}
        <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
            <button class="btn ok"
                    id="btn-save"
                    type="submit">{{ __('Guardar') }}</button>

            @if (session('status') === 'profile-updated')
                <span id="saved-hint"
                      class="muted"
                      aria-live="polite">{{ __('Guardado.') }}</span>
            @else
                <span id="saved-hint"
                      class="muted"
                      aria-live="polite"
                      style="display:none"></span>
            @endif
        </div>
    </form>
</section>

@push('scripts')
    @once
        <script>
            (function () {
                const $ = (id) => document.getElementById(id);

                // Contador accesible (usa maxlength si existe)
                function bindCounter(input, outId) {
                    const out = $(outId); if (!input || !out) return;
                    const max = Number(input.getAttribute('maxlength') || '0') || null;
                    const update = () => {
                        const len = (input.value || '').length;
                        out.textContent = max ? `${len}/${max}` : `${len}`;
                    };
                    input.addEventListener('input', update, { passive: true });
                    update();
                }
                bindCounter($('name'), 'name_count');

                // Anti doble submit + feedback
                const form = document.getElementById('profile-info-form');
                const btn = document.getElementById('btn-save');
                const hint = document.getElementById('saved-hint');

                form?.addEventListener('submit', () => {
                    if (btn) {
                        btn.disabled = true;
                        btn.setAttribute('aria-disabled', 'true');
                        btn.textContent = @json(__('Guardando…'));
                    }
                });

                // Si vino "profile-updated", ocultar el hint a los 2s (sin Alpine)
                if (hint && hint.textContent) {
                    setTimeout(() => { hint.style.display = 'none'; }, 2000);
                }
            })();
        </script>
    @endonce
@endpush