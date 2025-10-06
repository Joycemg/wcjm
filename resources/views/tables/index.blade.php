{{-- resources/views/mesas/index.blade.php --}}
@extends('layouts.app')
@section('title', __('Mesas') . ' · ' . config('app.name', 'La Taberna'))

@section('content')
    @php
        $total = method_exists($tables, 'total') ? $tables->total() : (is_countable($tables) ? count($tables) : null);
        $hasCreate = \Illuminate\Support\Facades\Route::has('mesas.create');
      @endphp

    <header>
        <h1 style="color:var(--maroon);margin:0">
            {{ __('Mesas publicadas') }}
            @if(!is_null($total)) <small class="muted">({{ $total }})</small>@endif
        </h1>
    </header>

    <div class="divider"
         role="separator"
         aria-hidden="true"></div>

    <div class="cards">
        @forelse($tables as $mesa)
            @includeFirst(['mesas._card', 'tables._card'], ['mesa' => $mesa, 'myMesaId' => $myMesaId ?? null])
        @empty
            <div class="card">
                <p class="muted"
                   style="margin:.1rem 0">{{ __('No hay mesas aún.') }}</p>
                @auth
                    @if ($hasCreate)
                        <p style="margin:.5rem 0 0">
                            <a href="{{ route('mesas.create') }}"
                               class="btn">{{ __('Crear una mesa') }}</a>
                        </p>
                    @endif
                @endauth
            </div>
        @endforelse
    </div>

    <div class="divider"></div>

    @if(method_exists($tables, 'withQueryString'))
        {{ $tables->withQueryString()->links() }}
    @endif
@endsection

@push('scripts')
    @once
        <script>
            (() => {
                // Usa la hora del servidor inyectada en el layout (window.mesasNowMs)
                const nowMs = (typeof window.mesasNowMs === 'function') ? window.mesasNowMs : () => Date.now();

                // Habilita botones exactamente al llegar la hora de apertura.
                // Requisito en el partial de la card:
                //   <button class="btn js-enable-at" disabled data-enable-at="[epoch_seconds]">...</button>
                const timers = new Map(); // key -> timeoutId

                const enableIfDue = (btn) => {
                    const at = Number(btn.dataset.enableAt || '0'); // segundos epoch
                    if (!at) return false;
                    const due = (at * 1000) <= nowMs();
                    if (due) {
                        btn.removeAttribute('disabled');
                        btn.classList.remove('disabled');
                        // Actualiza badge "Cerrada" -> "Abierta" si existe
                        const card = btn.closest('.mesa-card');
                        const badge = card?.querySelector('.badge.closed');
                        if (badge) {
                            badge.textContent = @json(__('Abierta'));
                            badge.classList.remove('closed');
                            badge.classList.add('open');
                        }
                    }
                    return due;
                };

                const scheduleBtn = (btn) => {
                    const card = btn.closest('.mesa-card');
                    const key = card?.dataset?.mesaId || Math.random().toString(36).slice(2);

                    if (timers.has(key)) { clearTimeout(timers.get(key)); timers.delete(key); }

                    const at = Number(btn.dataset.enableAt || '0');
                    if (!at) return;

                    const delta = (at * 1000) - nowMs();
                    if (delta <= 0) { enableIfDue(btn); return; }

                    const tId = setTimeout(() => {
                        // chequeo doble por si el tab estuvo suspendido
                        if (!enableIfDue(btn)) {
                            setTimeout(() => enableIfDue(btn), 1200);
                        }
                        timers.delete(key);
                    }, Math.min(Math.max(delta + 300, 300), 18_000_000)); // +300ms tolerancia, cap 5h
                    timers.set(key, tId);
                };

                const setupAll = () => {
                    document.querySelectorAll('.js-enable-at[disabled][data-enable-at]').forEach((btn) => {
                        if (!enableIfDue(btn)) scheduleBtn(btn);
                    });
                };

                // Init liviano
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(setupAll, { timeout: 1000 });
                } else {
                    setTimeout(setupAll, 0);
                }

                // Reajustes al volver foco/visibilidad (por suspensión de timers)
                addEventListener('focus', setupAll, { passive: true });
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') setupAll();
                }, { passive: true });

                // Si re-renderizás por AJAX los cards, invocá:
                window.refreshMesasButtons = setupAll;
            })();
        </script>
    @endonce
@endpush