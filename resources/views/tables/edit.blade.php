@extends('layouts.app')

@section('title', __('Editar mesa') . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        /* ===== Mesas · Edit (usa tokens globales) ===== */
        .form-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 0;
            padding: clamp(.9rem, 2.5vw, 1.15rem);
            box-shadow: var(--shadow-sm)
        }

        .muted {
            color: var(--muted)
        }

        .alert {
            margin: 1rem 0;
            padding: .75rem;
            border-radius: 0;
            background: #FCECEC;
            border: 1px solid #F3B9B9;
            color: #7f1d1d
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr))
        }

        .form-control {
            width: 100%;
            padding: .65rem .8rem;
            border: 1px solid var(--line);
            border-radius: 0;
            background: #fff;
            color: var(--ink)
        }

        .form-control:focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px
        }

        .textarea {
            width: 100%;
            min-height: 140px;
            padding: .75rem;
            border-radius: 0;
            border: 1px solid var(--line);
            resize: vertical;
            background: #fff;
            color: var(--ink);
            line-height: 1.55
        }

        .help {
            font-size: .9rem
        }

        .divider {
            margin: 1rem 0;
            border-top: 1px solid var(--line)
        }

        .preview {
            display: block;
            max-width: 220px;
            height: auto;
            border-radius: 0;
            object-fit: cover;
            border: 1px solid var(--line);
            background: #fff
        }

        .title {
            color: var(--ink);
            margin-top: 0;
            font-weight: 800
        }

        .badge-hint {
            font-size: .85rem
        }

        @media (prefers-color-scheme:dark) {

            .form-control,
            .textarea {
                background: #0f172a;
                color: var(--ink)
            }
        }
    </style>
@endpush

@section('content')
    @php
        use Illuminate\Support\Facades\Route as LRoute;
        $tz = $tz ?? config('app.display_timezone', config('app.timezone', 'UTC'));
        $managerCandidates = collect($managerCandidates ?? []);
        $showUrl = LRoute::has('mesas.show') ? route('mesas.show', $mesa) : url('/mesas/' . ($mesa->id ?? ''));
    @endphp

    <div class="form-card">
        <h2 class="title">{{ __('Editar mesa') }}</h2>

        @if ($errors->any())
            <div class="alert"
                 role="alert">
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
            @csrf @method('PUT')

            {{-- Título --}}
            <div>
                <label for="title">{{ __('Título') }}</label>
                <input id="title"
                       name="title"
                       class="form-control"
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
                       class="muted help">{{ __('Máximo :n caracteres.', ['n' => 120]) }}</small>
                <div id="title_count"
                     class="muted help"
                     aria-live="polite"></div>
                @error('title') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Capacidad --}}
            <div>
                <label for="capacity">{{ __('Capacidad') }}</label>
                <input id="capacity"
                       class="form-control"
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
                       class="muted help">{{ __('Cantidad máxima de jugadores (1–1000).') }}</small>
                @error('capacity') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Descripción --}}
            <div style="grid-column:1/-1">
                <label for="description">{{ __('Descripción') }}</label>
                <textarea id="description"
                          name="description"
                          class="textarea"
                          rows="4"
                          maxlength="2000"
                          aria-describedby="description_help description_count"
                          @error('description')
                              aria-invalid="true"
                          @enderror>{{ old('description', $mesa->description) }}</textarea>
                <small id="description_help"
                       class="muted help">{{ __('Opcional. Máximo :n caracteres.', ['n' => 2000]) }}</small>
                <div id="description_count"
                     class="muted help"
                     aria-live="polite"></div>
                @error('description') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Link --}}
            <div>
                <label for="join_url">{{ __('Enlace para la mesa (opcional)') }}</label>
                <input id="join_url"
                       class="form-control"
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
                       class="muted help">{{ __('Guardá un enlace directo (Discord, Roll20, Foundry, etc.).') }}</small>
                @error('join_url') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Encargado --}}
            <div>
                <label for="manager_id">{{ __('Encargado de la mesa (opcional)') }}</label>
                <input id="manager_id"
                       class="form-control"
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
                       class="muted help">{{ __('Tipeá el ID o elegilo de la lista de usuarios recientes.') }}</small>
                @error('manager_id') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror

                <datalist id="manager-candidates">
                    @foreach($managerCandidates as $candidate)
                        @php
                            $label = trim(collect([$candidate->name, $candidate->username ? '@' . $candidate->username : null, $candidate->email])->filter()->implode(' · '));
                            $label = $label !== '' ? $label : 'ID ' . $candidate->id;
                          @endphp
                        <option value="{{ $candidate->id }}"
                                label="{{ $label }}"></option>
                    @endforeach
                </datalist>
            </div>

            {{-- Nota privada --}}
            <div style="grid-column:1/-1">
                <label for="manager_note">{{ __('Nota interna (opcional)') }}</label>
                <textarea id="manager_note"
                          name="manager_note"
                          class="textarea"
                          rows="3"
                          maxlength="2000"
                          aria-describedby="manager_note_help"
                          @error('manager_note')
                              aria-invalid="true"
                          @enderror>{{ old('manager_note', $mesa->manager_note) }}</textarea>
                <small id="manager_note_help"
                       class="muted help">{{ __('Sólo la verá quien administre la mesa.') }}</small>
                @error('manager_note') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Imagen actual / reemplazo --}}
            <div>
                <label>{{ __('Imagen actual') }}</label>
                <div class="muted"
                     style="margin-bottom:.4rem">
                    @if (!empty($mesa->image_url_resolved))
                        <img id="image-current"
                             src="{{ $mesa->image_url_resolved }}"
                             alt="{{ __('Imagen de :title', ['title' => $mesa->title]) }}"
                             class="preview"
                             loading="lazy"
                             decoding="async"
                             width="220"
                             height="124">
                    @else
                        <span id="image-current-none">{{ __('Sin imagen') }}</span>
                    @endif
                </div>

                <label for="image">{{ __('Reemplazar imagen') }}</label>
                <input id="image"
                       class="form-control"
                       type="file"
                       name="image"
                       accept="image/png,image/jpeg,image/webp"
                       aria-describedby="image_help image_note"
                       @error('image')
                           aria-invalid="true"
                       @enderror>
                <small id="image_help"
                       class="muted help">{{ __('JPG/PNG/WebP, máx. 2MB, 16:9 recomendado.') }}</small>
                <div id="image_note"
                     class="muted help"></div>

                @if (!empty($mesa->image_path) || !empty($mesa->image_url))
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
                @error('image') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Estado --}}
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
                <small class="muted help">{{ __('Si hay apertura futura, autoabrirá a la hora programada.') }}</small>
                @error('is_open') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Apertura programada --}}
            <div>
                <label for="opens_at">{{ __('Apertura programada') }}</label>
                <input id="opens_at"
                       class="form-control"
                       type="datetime-local"
                       name="opens_at"
                       value="{{ $opensAtValue }}"
                       step="60"
                       aria-describedby="opens_help opens_hint"
                       @error('opens_at')
                           aria-invalid="true"
                       @enderror>
                <small id="opens_help"
                       class="muted help">{{ __('Proponemos HOY 10:15 (:tz).', ['tz' => $tz]) }}</small>
                <div id="opens_hint"
                     class="muted help"></div>

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
                @error('opens_at') <div class="badge-hint"
                 style="color:#b91c1c">{{ $message }}</div> @enderror
            </div>

            {{-- Acciones --}}
            <div style="grid-column:1/-1;display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
                <button class="btn ok"
                        id="btn-submit"
                        type="submit">{{ __('Guardar cambios') }}</button>
                <a class="btn"
                   href="{{ $showUrl }}">{{ __('Volver') }}</a>
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
                        type="submit">🗑️ {{ __('Eliminar') }}</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    @once
        <script>
            (() => {
                const $ = id => document.getElementById(id);
                const bindCounter = (input, outId) => { const out = $(outId); if (!input || !out) return; const max = Number(input.getAttribute('maxlength') || '0') || null; const update = () => { const len = (input.value || '').length; out.textContent = max ? `${len}/${max}` : `${len}` }; input.addEventListener('input', update, { passive: true }); update(); };
                bindCounter($('title'), 'title_count'); bindCounter($('description'), 'description_count');

                const MAX_BYTES = 2 * 1024 * 1024; const inputImage = $('image'), noteImage = $('image_note'), chkRemove = $('remove_image');
                function clearFile() { if (!inputImage) return; inputImage.value = ''; inputImage.setCustomValidity(''); if (noteImage) noteImage.textContent = ''; }
                function handleRemoveToggle() { if (!inputImage || !chkRemove) return; const on = chkRemove.checked; inputImage.disabled = on; if (on) clearFile(); }
                chkRemove?.addEventListener('change', handleRemoveToggle, { passive: true }); handleRemoveToggle();
                inputImage?.addEventListener('change', e => {
                    const f = e.target.files?.[0]; if (!f) { inputImage.setCustomValidity(''); if (noteImage) noteImage.textContent = ''; return; }
                    if (f.size > MAX_BYTES) { inputImage.setCustomValidity(@json(__('La imagen supera 2 MB.'))); if (noteImage) noteImage.textContent = @json(__('Archivo demasiado grande (máx. 2 MB).')); } else { inputImage.setCustomValidity(''); if (noteImage) noteImage.textContent = ''; }
                    const cur = $('image-current'), none = $('image-current-none'); const url = URL.createObjectURL(f); if (cur) { cur.src = url; cur.style.display = 'block'; if (none) none.style.display = 'none'; inputImage.addEventListener('formdata', () => URL.revokeObjectURL(url), { once: true }); }
                });

                const meta = document.querySelector('meta[name="server-now-ms"]'); const serverNowMs = meta ? parseInt(meta.content, 10) : Date.now(); const skew = serverNowMs - Date.now(); const nowFromServer = () => new Date(Date.now() + skew);
                const opens = $('opens_at'), tzHint = $('opens_hint'); const pad = n => String(n).padStart(2, '0'); const toLocal = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                $('btn-1015-today')?.addEventListener('click', () => { const d = nowFromServer(); d.setHours(10, 15, 0, 0); opens.value = toLocal(d); announce(); }, { passive: true });
                $('btn-now-opens')?.addEventListener('click', () => { const d = nowFromServer(); d.setSeconds(0, 0); opens.value = toLocal(d); announce(); }, { passive: true });
                $('btn-clear-opens')?.addEventListener('click', () => { opens.value = ''; announce(); }, { passive: true });
                function announce() { if (!tzHint) return; if (!opens.value) { tzHint.textContent = ''; return; } try { const d = new Date(opens.value); tzHint.textContent = d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch { tzHint.textContent = ''; } }
                announce();

                const form = $('mesa-edit-form'), subm = $('btn-submit'); form?.addEventListener('submit', () => { if (subm) { subm.disabled = true; subm.setAttribute('aria-disabled', 'true'); subm.textContent = @json(__('Guardando…')); } });
            })();
        </script>
    @endonce
@endpush