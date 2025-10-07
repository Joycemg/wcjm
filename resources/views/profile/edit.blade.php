{{-- resources/views/profile/edit.blade.php --}}
@extends('layouts.app')
@section('title', __('Mi perfil') . ' · ' . config('app.name', 'La Taberna'))

@section('content')
    @php
        /** @var \App\Models\User $user */
        $auth = $user; // viene del controller

        $defaultAvatar = asset(config('auth.avatars.default', 'images/avatar-default.svg'));
        $baseAvatar = $auth->avatar_url ?? $defaultAvatar;
        $ver = optional($auth->updated_at)->timestamp;
        $avatar = $baseAvatar . ($ver ? ('?v=' . $ver) : '');

        // URL del perfil público (si existe la ruta)
        $profileUrl = Route::has('profile.show')
            ? (isset($user->profile_param) ? route('profile.show', $user->profile_param) : route('profile.show', $user))
            : null;
    @endphp

    <div class="card"
         style="max-width:720px;margin-inline:auto">
        <h2 style="color:var(--maroon);margin:0">{{ __('Mi perfil') }}</h2>
        <div class="divider"></div>

        {{-- Flash messages --}}
        @if(session('ok'))
            <div class="flash"
                 role="status"
                 aria-live="polite">{{ session('ok') }}</div>
        @endif
        @if(session('err'))
            <div class="flash flash-error"
                 role="alert">{{ session('err') }}</div>
        @endif

        {{-- Errores de validación --}}
        @if ($errors->any())
            <div role="alert"
                 style="margin:.75rem 0;padding:.75rem;border:1px solid #f87171;border-radius: 0;background:#fff5f5;color:#7f1d1d">
                <strong style="display:block;margin-bottom:.25rem">{{ __('Revisá los campos:') }}</strong>
                <ul style="margin:0;padding-left:1rem">
                    @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
            </div>
        @endif

        <form id="profile-edit-form"
              method="POST"
              action="{{ route('profile.update') }}"
              enctype="multipart/form-data"
              class="grid"
              novalidate>
            @csrf
            @method('PATCH')

            {{-- Avatar --}}
            <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap">
                <img id="avatar-preview"
                     src="{{ $avatar }}"
                     alt="{{ __('Avatar de :name', ['name' => $auth->name ?? $auth->username ?? 'User']) }}"
                     style="width:72px;height:72px;border-radius: 0;border:2px solid var(--line);object-fit:cover"
                     width="72"
                     height="72"
                     loading="lazy"
                     decoding="async">

                <div style="flex:1;min-width:260px">
                    <label for="avatar">{{ __('Avatar (archivo)') }}</label>
                    <input id="avatar"
                           type="file"
                           name="avatar"
                           accept="image/png,image/jpeg,image/webp,image/avif"
                           aria-describedby="avatar_help avatar_note"
                           @error('avatar')
                               aria-invalid="true"
                           @enderror>
                    <small id="avatar_help"
                           class="muted">{{ __('JPG/PNG/WebP/AVIF, máx. 4 MB') }}</small>
                    <div id="avatar_note"
                         class="muted"
                         style="font-size:.85rem;margin-top:.25rem"></div>
                    @error('avatar') <div class="text-danger">{{ $message }}</div> @enderror

                    <div class="muted"
                         style="margin:.5rem 0;text-align:center">— {{ __('o') }} —</div>

                    <label for="avatar_url">{{ __('Avatar desde link (URL)') }}</label>
                    <input id="avatar_url"
                           type="url"
                           name="avatar_url"
                           placeholder="https://ejemplo.com/mi_foto.jpg"
                           value="{{ old('avatar_url') }}"
                           aria-describedby="avatar_url_help"
                           inputmode="url"
                           pattern="https?://.*">
                    <small id="avatar_url_help"
                           class="muted">{{ __('Si pegás un link, se ignorará el archivo.') }}</small>
                    @error('avatar_url') <div class="text-danger">{{ $message }}</div> @enderror

                    {{-- Quitar avatar --}}
                    <div style="margin-top:.6rem">
                        <label style="display:flex;gap:.4rem;align-items:center">
                            <input type="checkbox"
                                   id="remove_avatar"
                                   name="remove_avatar"
                                   value="1">
                            <span>{{ __('Quitar avatar actual') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Nombre --}}
            <div>
                <label for="name">{{ __('Nombre') }}</label>
                <input id="name"
                       name="name"
                       type="text"
                       required
                       minlength="1"
                       maxlength="120"
                       value="{{ old('name', $user->name) }}"
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

            {{-- Usuario --}}
            <div>
                <label for="username">{{ __('Usuario (opcional)') }}</label>
                <input id="username"
                       name="username"
                       minlength="3"
                       maxlength="32"
                       value="{{ old('username', $user->username) }}"
                       placeholder="ej: lataberna.master"
                       inputmode="latin"
                       pattern="^[A-Za-z0-9._-]+$"
                       title="{{ __('Letras, números, punto (.), guion (-) y guion bajo (_). Mínimo 3, máximo 32.') }}"
                       aria-describedby="username_help username_count"
                       @error('username')
                           aria-invalid="true"
                       @enderror>
                <small id="username_help"
                       class="muted">
                    {{ __('Hasta :n caracteres. Podés usar letras, números, punto (.), guion (-) y guion bajo (_).', ['n' => 32]) }}
                </small>
                <div id="username_count"
                     class="muted"
                     style="font-size:.8rem"
                     aria-live="polite"></div>
                @error('username') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Bio --}}
            <div style="grid-column:1/-1">
                <label for="bio">{{ __('Bio') }}</label>
                <textarea id="bio"
                          name="bio"
                          rows="4"
                          maxlength="2000"
                          placeholder="{{ __('Contanos qué te gusta jugar…') }}"
                          aria-describedby="bio_help bio_count"
                          @error('bio')
                              aria-invalid="true"
                          @enderror>{{ old('bio', $user->bio) }}</textarea>
                <small id="bio_help"
                       class="muted">{{ __('Opcional. Máximo :n caracteres.', ['n' => 2000]) }}</small>
                <div id="bio_count"
                     class="muted"
                     style="font-size:.8rem"
                     aria-live="polite"></div>
                @error('bio') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Acciones --}}
            <div style="grid-column:1/-1;display:flex;gap:.6rem;flex-wrap:wrap">
                <button class="btn ok"
                        id="btn-submit"
                        type="submit">{{ __('Guardar') }}</button>
                <a class="btn"
                   href="{{ route('home') }}">{{ __('Volver') }}</a>
                @if($profileUrl)
                    <a class="btn"
                       href="{{ $profileUrl }}">{{ __('Ver perfil') }}</a>
                @endif
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @once
        <script>
            (function () {
                const $ = id => document.getElementById(id);

                // ==== Contadores accesibles ====
                function bindCounter(input, outId) {
                    const out = $(outId);
                    if (!input || !out) return;
                    const max = Number(input.getAttribute('maxlength') || '0') || null;
                    const update = () => {
                        const len = (input.value || '').length;
                        out.textContent = max ? `${len}/${max}` : `${len}`;
                    };
                    input.addEventListener('input', update, { passive: true });
                    update();
                }
                bindCounter($('name'), 'name_count');
                bindCounter($('username'), 'username_count');
                bindCounter($('bio'), 'bio_count');

                // ==== Avatar: validación (4MB) + preview + exclusión archivo/URL + toggle "quitar" ====
                const MAX_BYTES = 4 * 1024 * 1024;
                const file = $('avatar');
                const urlInp = $('avatar_url');
                const note = $('avatar_note');
                const remove = $('remove_avatar');
                const prev = $('avatar-preview');

                function clearFile() {
                    if (file) {
                        file.value = '';
                        file.setCustomValidity('');
                    }
                    if (note) note.textContent = '';
                }
                function clearUrl() {
                    if (urlInp) urlInp.value = '';
                }
                function setPreviewFromFile(f) {
                    if (!f || !prev) return;
                    if (/^image\//.test(f.type)) {
                        const url = URL.createObjectURL(f);
                        prev.src = url;
                        file.addEventListener('formdata', () => URL.revokeObjectURL(url), { once: true });
                    }
                }
                function setPreviewFromUrl(u) {
                    if (!prev || !u) return;
                    prev.src = u;
                }

                // Mutual exclusion: if user checks "remove", disable both inputs
                function onRemoveToggle() {
                    const on = !!remove?.checked;
                    if (file) file.disabled = on;
                    if (urlInp) urlInp.disabled = on;
                    if (on) {
                        clearFile(); clearUrl();
                        if (prev) prev.style.opacity = '.6';
                    } else if (prev) {
                        prev.style.opacity = '';
                    }
                }
                remove?.addEventListener('change', onRemoveToggle, { passive: true });
                onRemoveToggle();

                // When user picks a file → clear URL, preview file
                file?.addEventListener('change', (e) => {
                    const f = e.target.files?.[0];
                    if (!f) {
                        file.setCustomValidity('');
                        if (note) note.textContent = '';
                        return;
                    }
                    // size check
                    if (f.size > MAX_BYTES) {
                        file.setCustomValidity(@json(__('El archivo supera 4 MB.')));
                        if (note) note.textContent = @json(__('Archivo demasiado grande (máx. 4 MB).'));
                    } else {
                        file.setCustomValidity('');
                        if (note) note.textContent = '';
                    }
                    if (urlInp && urlInp.value) clearUrl();
                    setPreviewFromFile(f);
                    // Si estaba marcado "quitar", desmarcar
                    if (remove && remove.checked) { remove.checked = false; onRemoveToggle(); }
                }, { passive: false });

                // When user types a URL → clear file, preview URL (si parece http/https)
                urlInp?.addEventListener('input', () => {
                    const v = (urlInp.value || '').trim();
                    if (v.startsWith('http://') || v.startsWith('https://')) {
                        clearFile();
                        setPreviewFromUrl(v);
                        if (remove && remove.checked) { remove.checked = false; onRemoveToggle(); }
                    }
                }, { passive: true });

                // ==== Anti doble submit ====
                const form = document.getElementById('profile-edit-form');
                const btn = document.getElementById('btn-submit');
                form?.addEventListener('submit', () => {
                    if (btn) {
                        btn.disabled = true;
                        btn.setAttribute('aria-disabled', 'true');
                        btn.textContent = @json(__('Guardando…'));
                    }
                });
            })();
        </script>
    @endonce
@endpush