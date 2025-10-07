{{-- resources/views/mesas/edit.blade.php --}}
@extends('layouts.app')
@section('title', __('Editar mesa') . ' · ' . config('app.name', 'La Taberna'))

@section('content')
    <div class="card">
        <h2 style="color:var(--maroon);margin-top:0">{{ __('Editar mesa') }}</h2>

        @if ($errors->any())
            <div role="alert"
                 style="margin:1rem 0;padding:.75rem;border:1px solid #f87171;border-radius:.5rem;background:#fff5f5;color:#7f1d1d">
                <strong style="display:block;margin-bottom:.25rem">{{ __('Revisá los campos:') }}</strong>
                <ul style="margin:0;padding-left:1rem">
                    @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
            </div>
        @endif

        <form id="mesa-edit-form"
              method="POST"
              action="{{ route('mesas.update', $mesa) }}"
              enctype="multipart/form-data"
              class="grid"
              novalidate>
            @csrf
            @method('PATCH')

            {{-- Título --}}
            <div>
                <label for="title">{{ __('Título') }}</label>
                <input id="title"
                       name="title"
                       type="text"
                       required
                       maxlength="120"
                       value="{{ old('title', $mesa->title) }}"
                       aria-describedby="title_help title_count"
                       inputmode="latin"
                       pattern=".*\S.*"
                       @error('title')
                           aria-invalid="true"
                       @enderror>
                <small id="title_help"
                       class="muted">{{ __('Máximo :n caracteres.', ['n' => 120]) }}</small>
                <div id="title_count"
                     class="muted"
                     style="font-size:.8rem"
                     aria-live="polite"></div>
                @error('title') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Capacidad --}}
            <div>
                <label for="capacity">{{ __('Capacidad') }}</label>
                <input id="capacity"
                       type="number"
                       name="capacity"
                       min="1"
                       max="1000"
                       step="1"
                       inputmode="numeric"
                       required
                       value="{{ old('capacity', $mesa->capacity) }}"
                       aria-describedby="capacity_help"
                       @error('capacity')
                           aria-invalid="true"
                       @enderror>
                <small id="capacity_help"
                       class="muted">{{ __('Cantidad máxima de jugadores (1–1000).') }}</small>
                @error('capacity') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            @php
                $managerCandidates = collect($managerCandidates ?? []);
            @endphp

            {{-- Descripción --}}
            <div style="grid-column:1/-1">
                <label for="description">{{ __('Descripción') }}</label>
                <textarea id="description"
                          name="description"
                          rows="4"
                          maxlength="2000"
                          aria-describedby="description_help description_count"
                          @error('description')
                              aria-invalid="true"
                          @enderror>{{ old('description', $mesa->description) }}</textarea>
                <small id="description_help"
                       class="muted">{{ __('Opcional. Máximo :n caracteres.', ['n' => 2000]) }}</small>
                <div id="description_count"
                     class="muted"
                     style="font-size:.8rem"
                     aria-live="polite"></div>
                @error('description') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Link para unirse / manual --}}
            <div>
                <label for="join_url">{{ __('Enlace para la mesa (opcional)') }}</label>
                <input id="join_url"
                       type="url"
                       name="join_url"
                       value="{{ old('join_url', $mesa->join_url) }}"
                       maxlength="2048"
                       placeholder="https://"
                       aria-describedby="join_url_help"
                       @error('join_url')
                           aria-invalid="true"
                       @enderror>
                <small id="join_url_help"
                       class="muted">{{ __('Guardá un enlace directo a la sesión (Discord, Roll20, Foundry, etc.).') }}</small>
                @error('join_url') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Encargado / dueño de mesa --}}
            <div>
                <label for="manager_id">{{ __('Encargado de la mesa (opcional)') }}</label>
                <input id="manager_id"
                       type="number"
                       name="manager_id"
                       list="manager-candidates"
                       inputmode="numeric"
                       min="1"
                       step="1"
                       value="{{ old('manager_id', $mesa->manager_id) }}"
                       aria-describedby="manager_help"
                       @error('manager_id')
                           aria-invalid="true"
                       @enderror>
                <small id="manager_help"
                       class="muted">{{ __('Podés tipear el ID directamente o elegirlo de la lista de usuarios recientes.') }}</small>
                @error('manager_id') <div class="text-danger">{{ $message }}</div> @enderror

                <datalist id="manager-candidates">
                    @foreach($managerCandidates as $candidate)
                        @php
                            $label = trim(collect([
                                $candidate->name,
                                $candidate->username ? '@' . $candidate->username : null,
                                $candidate->email,
                            ])->filter()->implode(' · '));
                            $label = $label !== '' ? $label : 'ID ' . $candidate->id;
                        @endphp
                        <option value="{{ $candidate->id }}"
                                label="{{ $label }}"></option>
                    @endforeach
                </datalist>
            </div>

            {{-- Nota privada para el encargado --}}
            <div style="grid-column:1/-1">
                <label for="manager_note">{{ __('Nota interna para el encargado (opcional)') }}</label>
                <textarea id="manager_note"
                          name="manager_note"
                          rows="3"
                          maxlength="2000"
                          aria-describedby="manager_note_help"
                          @error('manager_note')
                              aria-invalid="true"
                          @enderror>{{ old('manager_note', $mesa->manager_note) }}</textarea>
                <small id="manager_note_help"
                       class="muted">{{ __('Sólo la verá quien administre la mesa.') }}</small>
                @error('manager_note') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Imagen actual / reemplazo --}}
            <div>
                <label>{{ __('Imagen actual') }}</label>
                <div class="muted"
                     style="margin-bottom:.4rem">
                    @if (!empty($mesa->image_url))
                        <img id="image-current"
                             src="{{ $mesa->image_url }}"
                             alt="{{ __('Imagen de :title', ['title' => $mesa->title]) }}"
                             style="display:block;max-width:220px;height:auto;border-radius:.5rem;object-fit:cover">
                    @else
                        <span id="image-current-none">{{ __('Sin imagen') }}</span>
                    @endif
                </div>

                <label for="image">{{ __('Reemplazar imagen') }}</label>
                <input id="image"
                       type="file"
                       name="image"
                       accept="image/png,image/jpeg,image/webp"
                       aria-describedby="image_help image_note"
                       @error('image')
                           aria-invalid="true"
                       @enderror>
                <small id="image_help"
                       class="muted">
                    {{ __('Usá JPG/PNG/WebP. Tamaño máx. 2MB. Recomendado 16:9 (p. ej. 1280×720).') }}
                </small>
                <div id="image_note"
                     class="muted"
                     style="font-size:.85rem;margin-top:.25rem"></div>

                @if (!empty($mesa->image_url))
                    <div style="margin-top:.4rem">
                        <label style="display:flex;gap:.4rem;align-items:center">
                            <input id="remove_image"
                                   type="checkbox"
                                   name="remove_image"
                                   value="1">
                            <span>{{ __('Quitar imagen actual') }}</span>
                        </label>
                    </div>
                @endif

                @error('image') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Apertura manual --}}
            <div>
                <input type="hidden"
                       name="is_open"
                       value="0">
                <label for="is_open"
                       style="display:flex;gap:.5rem;align-items:center">
                    <input id="is_open"
                           type="checkbox"
                           name="is_open"
                           value="1"
                           {{ old('is_open', $mesa->is_open) ? 'checked' : '' }}>
                    <span>{{ __('Abrir manualmente') }}</span>
                </label>
                <small
                       class="muted">{{ __('Si hay apertura futura, quedará lista para autoabrir a la hora programada.') }}</small>
                @error('is_open') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Apertura programada --}}
            <div>
                <label for="opens_at">{{ __('Apertura programada') }}</label>
                <input id="opens_at"
                       type="datetime-local"
                       name="opens_at"
                       value="{{ $opensAtValue }}"
                       step="60"
                       aria-describedby="opens_help opens_hint"
                       @error('opens_at')
                           aria-invalid="true"
                       @enderror>
                <small id="opens_help"
                       class="muted">
                    {{ __('Proponemos HOY 10:15 (:tz). Podés cambiarlo.', ['tz' => $tz]) }}
                </small>
                <div id="opens_hint"
                     class="muted"
                     style="font-size:.85rem"></div>

                <div style="margin-top:.4rem;display:flex;gap:.5rem;flex-wrap:wrap">
                    <button type="button"
                            class="btn"
                            id="btn-1015-today">{{ __('Hoy 10:15') }}</button>
                    <button type="button"
                            class="btn"
                            id="btn-now-opens">{{ __('Ahora') }}</button>
                    <button type="button"
                            class="btn"
                            id="btn-clear-opens">{{ __('Quitar hora') }}</button>
                </div>
                @error('opens_at') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Acciones --}}
            <div style="grid-column:1/-1;display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
                <button class="btn ok"
                        id="btn-submit"
                        type="submit">{{ __('Guardar cambios') }}</button>
                <a class="btn"
                   href="{{ route('mesas.show', $mesa) }}">{{ __('Volver') }}</a>
            </div>
        </form>

        <div class="divider"></div>

        {{-- Eliminar --}}
        @if (Route::has('mesas.destroy'))
            <form method="POST"
                  action="{{ route('mesas.destroy', $mesa) }}"
                  onsubmit="return confirm('{{ __('¿Eliminar mesa?') }}')"
                  style="display:inline">
                @csrf @method('DELETE')
                <button class="btn danger"
                        type="submit">{{ __('Eliminar') }}</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    @once
        <script>
            (() => {
                const $ = (id) => document.getElementById(id);

                // ==== Contadores accesibles ====
                const bindCounter = (input, outId) => {
                    const out = $(outId);
                    if (!input || !out) return;
                    const max = Number(input.getAttribute('maxlength') || '0') || null;
                    const update = () => {
                        const len = (input.value || '').length;
                        out.textContent = max ? `${len}/${max}` : `${len}`;
                    };
                    input.addEventListener('input', update, { passive: true });
                    update();
                };
                bindCounter($('title'), 'title_count');
                bindCounter($('description'), 'description_count');

                // ==== Imagen: validación tamaño + preview ====
                const MAX_BYTES = 2 * 1024 * 1024; // 2MB
                const inputImage = $('image');
                const noteImage = $('image_note');
                const chkRemove = $('remove_image');

                function clearFile() {
                    if (!inputImage) return;
                    inputImage.value = '';
                    inputImage.setCustomValidity('');
                    if (noteImage) noteImage.textContent = '';
                    // Restaurar preview si había actual
                    const cur = document.getElementById('image-current');
                    const none = document.getElementById('image-current-none');
                    if (cur) cur.style.display = 'block';
                    if (none) none.style.display = '';
                }

                function handleRemoveToggle() {
                    if (!inputImage || !chkRemove) return;
                    const on = chkRemove.checked;
                    inputImage.disabled = on;
                    if (on) clearFile();
                }
                chkRemove?.addEventListener('change', handleRemoveToggle, { passive: true });
                handleRemoveToggle();

                inputImage?.addEventListener('change', (e) => {
                    const f = e.target.files?.[0];
                    if (!f) { inputImage.setCustomValidity(''); if (noteImage) noteImage.textContent = ''; return; }

                    if (f.size > MAX_BYTES) {
                        inputImage.setCustomValidity(@json(__('La imagen supera 2 MB.')));
                        if (noteImage) noteImage.textContent = @json(__('Archivo demasiado grande (máx. 2 MB).'));
                    } else {
                        inputImage.setCustomValidity('');
                        if (noteImage) noteImage.textContent = '';
                    }

                    // Preview rápida
                    const cur = document.getElementById('image-current');
                    const none = document.getElementById('image-current-none');
                    const url = URL.createObjectURL(f);
                    if (cur) {
                        cur.src = url;
                        cur.style.display = 'block';
                        none && (none.style.display = 'none');
                        // liberar objeto al enviar o al limpiar
                        inputImage.addEventListener('formdata', () => URL.revokeObjectURL(url), { once: true });
                    } else if (none) {
                        none.style.display = 'none';
                    }
                }, { passive: false });

                // ==== Apertura programada: atajos usando hora del servidor ====
                const opens = $('opens_at');
                const tzHint = $('opens_hint');

                const pad = (n) => String(n).padStart(2, '0');
                const toLocal = (d) =>
                    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

                const nowFromServer = () => {
                    // usa el reloj del servidor expuesto por el layout (opción A)
                    const ms = (typeof window.mesasNowMs === 'function') ? window.mesasNowMs() : Date.now();
                    return new Date(ms);
                };

                const set1015Today = () => {
                    const d = nowFromServer();
                    d.setHours(10, 15, 0, 0);
                    opens.value = toLocal(d);
                    announceOpens();
                };
                const setNow = () => {
                    const d = nowFromServer();
                    d.setSeconds(0, 0);
                    opens.value = toLocal(d);
                    announceOpens();
                };
                const clearOpens = () => { opens.value = ''; announceOpens(); };

                $('btn-1015-today')?.addEventListener('click', set1015Today, { passive: true });
                $('btn-now-opens')?.addEventListener('click', setNow, { passive: true });
                $('btn-clear-opens')?.addEventListener('click', clearOpens, { passive: true });

                function announceOpens() {
                    if (!tzHint) return;
                    if (!opens.value) { tzHint.textContent = ''; return; }
                    // Muestra texto legible local del navegador (orientativo)
                    try {
                        const d = new Date(opens.value);
                        tzHint.textContent = d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
                    } catch (_) { tzHint.textContent = ''; }
                }
                announceOpens();

                // ==== Anti doble submit ====
                const form = document.getElementById('mesa-edit-form');
                const submitBtn = document.getElementById('btn-submit');
                form?.addEventListener('submit', () => {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.setAttribute('aria-disabled', 'true');
                        submitBtn.textContent = @json(__('Guardando…'));
                    }
                });

            })();
        </script>
    @endonce
@endpush