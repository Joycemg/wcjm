{{-- resources/views/mesas/create.blade.php --}}
@extends('layouts.app')

@section('title', __('Nueva mesa') . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        :root {
            --muted: #6b7280;
            --maroon: #7b2d26;
            --border: #e5e7eb
        }

        .muted {
            color: var(--muted)
        }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: .75rem;
            padding: 1rem
        }

        .btn {
            display: inline-flex;
            gap: .5rem;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: .6rem;
            padding: .5rem .9rem
        }

        .btn.ok {
            background: #dcfce7;
            border-color: #22c55e
        }
    </style>
@endpush

@section('content')
    @php
        $tz = config('app.display_timezone', config('app.timezone', 'UTC'));
        $opensAtObj = filled(old('opens_at'))
            ? \Carbon\Carbon::parse(old('opens_at'), $tz)
            : \Carbon\Carbon::now($tz)->setTime(22, 15, 0);
        $opensAtValue = $opensAtObj->format('Y-m-d\TH:i');
        $managerCandidates = collect($managerCandidates ?? []);
    @endphp

    <div class="card">
        <h2 style="color:var(--maroon);margin-top:0">{{ __('Nueva mesa') }}</h2>

        @if ($errors->any())
            <div role="alert"
                 style="margin:1rem 0;padding:.75rem;border:1px solid #f87171;border-radius:.5rem;background:#fff5f5;color:#7f1d1d">
                <strong style="display:block;margin-bottom:.25rem">{{ __('Revisá los campos:') }}</strong>
                <ul style="margin:0;padding-left:1rem">
                    @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
            </div>
        @endif

        <form id="mesa-create-form"
              method="POST"
              action="{{ route('mesas.store') }}"
              enctype="multipart/form-data"
              class="grid"
              novalidate>
            @csrf

            {{-- Título --}}
            <div>
                <label for="title">{{ __('Título') }}</label>
                <input id="title"
                       name="title"
                       type="text"
                       required
                       maxlength="120"
                       value="{{ old('title') }}"
                       aria-describedby="title_help title_count"
                       inputmode="latin"
                       pattern=".*\S.*"
                       autofocus
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
                       value="{{ old('capacity', 4) }}"
                       required
                       aria-describedby="capacity_help"
                       @error('capacity')
                           aria-invalid="true"
                       @enderror>
                <small id="capacity_help"
                       class="muted">{{ __('Cantidad máxima de jugadores (1–1000).') }}</small>
                @error('capacity') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

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
                          @enderror>{{ old('description') }}</textarea>
                <small id="description_help"
                       class="muted">{{ __('Opcional. Máximo :n caracteres.', ['n' => 2000]) }}</small>
                <div id="description_count"
                     class="muted"
                     style="font-size:.8rem"
                     aria-live="polite"></div>
                @error('description') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Link --}}
            <div>
                <label for="join_url">{{ __('Enlace para la mesa (opcional)') }}</label>
                <input id="join_url"
                       type="url"
                       name="join_url"
                       value="{{ old('join_url') }}"
                       maxlength="2048"
                       placeholder="https://"
                       aria-describedby="join_url_help"
                       @error('join_url')
                           aria-invalid="true"
                       @enderror>
                <small id="join_url_help"
                       class="muted">{{ __('Podés pegar un link a Discord, Roll20, Foundry, etc.') }}</small>
                @error('join_url') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Encargado --}}
            <div>
                <label for="manager_id">{{ __('Encargado de la mesa (opcional)') }}</label>
                <input id="manager_id"
                       type="number"
                       name="manager_id"
                       list="manager-candidates"
                       inputmode="numeric"
                       min="1"
                       step="1"
                       value="{{ old('manager_id') }}"
                       aria-describedby="manager_help"
                       @error('manager_id')
                           aria-invalid="true"
                       @enderror>
                <small id="manager_help"
                       class="muted">{{ __('Ingresá el ID o elegilo de la lista.') }}</small>
                @error('manager_id') <div class="text-danger">{{ $message }}</div> @enderror

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
                          rows="3"
                          maxlength="2000"
                          aria-describedby="manager_note_help"
                          @error('manager_note')
                              aria-invalid="true"
                          @enderror>{{ old('manager_note') }}</textarea>
                <small id="manager_note_help"
                       class="muted">{{ __('Visible sólo para quien administra la mesa.') }}</small>
                @error('manager_note') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Imagen --}}
            <div>
                <label for="image">{{ __('Imagen (opcional)') }}</label>
                <input id="image"
                       type="file"
                       name="image"
                       accept="image/png,image/jpeg,image/webp"
                       aria-describedby="image_help image_note"
                       @error('image')
                           aria-invalid="true"
                       @enderror>
                <small id="image_help"
                       class="muted">{{ __('JPG/PNG/WebP, máx. 2MB, 16:9 recomendado.') }}</small>
                <div id="image_note"
                     class="muted"
                     style="font-size:.85rem;margin-top:.25rem"></div>

                <div id="image-preview-wrap"
                     style="margin-top:.5rem;display:none">
                    <img id="image-preview"
                         alt="{{ __('Vista previa') }}"
                         style="display:block;max-width:220px;height:auto;border-radius:.5rem;object-fit:cover"
                         width="220"
                         height="124">
                </div>

                @error('image') <div class="text-danger">{{ $message }}</div> @enderror
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
                           {{ old('is_open') ? 'checked' : '' }}>
                    <span>{{ __('Abrir manualmente ya') }}</span>
                </label>
                <small class="muted">{{ __('Si programás apertura futura, autoabrirá a esa hora.') }}</small>
                @error('is_open') <div class="text-danger">{{ $message }}</div> @enderror
            </div>

            {{-- Apertura programada --}}
            <div>
                <label for="opens_at">{{ __('Apertura programada (opcional)') }}</label>
                <input id="opens_at"
                       type="datetime-local"
                       name="opens_at"
                       value="{{ old('opens_at', $opensAtValue) }}"
                       step="60"
                       aria-describedby="opens_help opens_hint"
                       @error('opens_at')
                           aria-invalid="true"
                       @enderror>
                <small id="opens_help"
                       class="muted">{{ __('Proponemos HOY 22:15 (:tz).', ['tz' => $tz]) }}</small>
                <div id="opens_hint"
                     class="muted"
                     style="font-size:.85rem"></div>

                <div style="margin-top:.4rem;display:flex;gap:.5rem;flex-wrap:wrap">
                    <button type="button"
                            class="btn"
                            id="btn-2215-today">{{ __('Hoy 22:15') }}</button>
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
                        type="submit">{{ __('Crear mesa') }}</button>
                <a class="btn"
                   href="{{ route('home') }}">{{ __('Cancelar') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @once
        <script>
            (() => {
                const $ = (id) => document.getElementById(id);

                // Contadores
                const bindCounter = (input, outId) => {
                    const out = $(outId); if (!input || !out) return;
                    const max = Number(input.getAttribute('maxlength') || '0') || null;
                    const update = () => { const len = (input.value || '').length; out.textContent = max ? `${len}/${max}` : `${len}`; };
                    input.addEventListener('input', update, { passive: true }); update();
                };
                bindCounter($('title'), 'title_count');
                bindCounter($('description'), 'description_count');

                // Imagen 2MB + preview
                const MAX_BYTES = 2 * 1024 * 1024;
                const inputImage = $('image'), noteImage = $('image_note'), prevWrap = $('image-preview-wrap'), prevImg = $('image-preview');
                inputImage?.addEventListener('change', (e) => {
                    const f = e.target.files?.[0];
                    if (!f) { inputImage.setCustomValidity(''); if (noteImage) noteImage.textContent = ''; if (prevWrap) prevWrap.style.display = 'none'; return; }
                    if (f.size > MAX_BYTES) { inputImage.setCustomValidity(@json(__('La imagen supera 2 MB.'))); if (noteImage) noteImage.textContent = @json(__('Archivo demasiado grande (máx. 2 MB).')); }
                    else { inputImage.setCustomValidity(''); if (noteImage) noteImage.textContent = ''; }
                    if (/^image\//.test(f.type) && prevWrap && prevImg) {
                        const url = URL.createObjectURL(f);
                        prevImg.src = url; prevImg.width = 220; prevImg.height = 124;
                        prevWrap.style.display = 'block';
                        inputImage.addEventListener('formdata', () => URL.revokeObjectURL(url), { once: true });
                    }
                });

                // Apertura programada helpers
                const opens = $('opens_at'), tzHint = $('opens_hint');
                const pad = (n) => String(n).padStart(2, '0');
                const toLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const nowFromServer = () => (typeof window.mesasNowMs === 'function') ? new Date(window.mesasNowMs()) : new Date();

                $('btn-2215-today')?.addEventListener('click', () => { const d = nowFromServer(); d.setHours(22, 15, 0, 0); opens.value = toLocal(d); announce(); }, { passive: true });
                $('btn-now-opens')?.addEventListener('click', () => { const d = nowFromServer(); d.setSeconds(0, 0); opens.value = toLocal(d); announce(); }, { passive: true });
                $('btn-clear-opens')?.addEventListener('click', () => { opens.value = ''; announce(); }, { passive: true });

                function announce() { if (!tzHint) return; if (!opens.value) { tzHint.textContent = ''; return; } try { const d = new Date(opens.value); tzHint.textContent = d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch { tzHint.textContent = ''; } }
                announce();

                // Anti doble submit
                const form = $('mesa-create-form'), submitBtn = $('btn-submit');
                form?.addEventListener('submit', () => { if (submitBtn) { submitBtn.disabled = true; submitBtn.setAttribute('aria-disabled', 'true'); submitBtn.textContent = @json(__('Creando…')); } });
            })();
        </script>
    @endonce
@endpush