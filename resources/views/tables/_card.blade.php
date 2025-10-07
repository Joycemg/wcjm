{{-- resources/views/tables/_card.blade.php --}}
@php
    use Carbon\Carbon;
    use Carbon\CarbonInterface;

    $alreadyThis = $alreadyThis ?? (bool) ($mesa->already_signed ?? false);
    $myMesaId    = $myMesaId ?? null;
    $signedOther = auth()->check() && $myMesaId && (int)$myMesaId !== (int)$mesa->id;

    $isOpenNow = (bool) ($mesa->is_open_now ?? false);
    $capacity  = (int) ($mesa->capacity ?? 0);
    $signups   = (int) ($mesa->signups_count ?? 0);
    $isFull    = $signups >= $capacity && $capacity > 0;

    $label = $alreadyThis ? __('✖ Retirar') : ($isFull ? __('📝 Reservar') : __('🗳️ Votar'));

    $recent = $mesa->relationLoaded('recentSignups') ? $mesa->recentSignups : collect();
    $total  = $signups ?: $recent->count();
    $extra  = max(0, $total - $recent->count());

    $imageUrl = $mesa->image_url ?: asset('images/placeholder-game.jpg');

    $tz = config('app.display_timezone', config('app.timezone', 'UTC'));
    $opensTs = 0;
    if ($mesa->opens_at instanceof CarbonInterface) {
        $opensTs = (int) $mesa->opens_at->timestamp;
    } elseif (!empty($mesa->opens_at)) {
        try { $opensTs = (int) Carbon::parse((string) $mesa->opens_at)->timestamp; } catch (\Throwable $e) { $opensTs = 0; }
    }
@endphp

<article class="card-game mesa-card" id="mesa-card-{{ $mesa->id }}"
         data-mesa-id="{{ $mesa->id }}" data-opens-ts="{{ $opensTs }}"
         aria-labelledby="mesa-title-{{ $mesa->id }}">
  <a class="media" href="{{ route('mesas.show', $mesa) }}">
    <img src="{{ $imageUrl }}" alt="{{ __('Imagen de :title', ['title' => $mesa->title]) }}"
         loading="lazy" decoding="async" width="640" height="360">
    <span class="badge state {{ $isOpenNow ? 'open' : 'closed' }}" data-badge>
      {{ $isOpenNow ? __('Abierta') : __('Cerrada') }}
    </span>
  </a>

  <div class="body">
    <a id="mesa-title-{{ $mesa->id }}" href="{{ route('mesas.show', $mesa) }}" style="font-weight:700;color:var(--maroon)">
      {{ $mesa->title }}
    </a>

    <div class="badges" aria-label="{{ __('Estado de la mesa') }}">
      <span class="badge cap" data-cap="{{ $capacity }}">{{ __('Cap') }}: <b>{{ $capacity }}</b></span>
      <span class="badge votes">{{ __('Votos') }}: <b data-signups>{{ $signups }}</b></span>
      <span class="badge">
        {{ __('Creada') }}:
        <time datetime="{{ $mesa->created_at->toAtomString() }}">{{ $mesa->created_at->format('Y-m-d') }}</time>
      </span>
    </div>

    @if($mesa->opens_at)
      <div class="muted" style="font-size:.85rem" aria-live="polite">
        {{ __('Apertura') }}:
        <time datetime="{{ $mesa->opens_at->toAtomString() }}" data-countdown-ts="{{ $opensTs }}">
          {{ $mesa->opens_at->timezone($tz)->format('Y-m-d H:i') }}
        </time>
        <span class="count" data-countdown-label></span>
      </div>
    @endif

    @if($capacity > 0)
      @php $pct = min(100, (int) round(($signups / max(1,$capacity)) * 100)); @endphp
      <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $capacity }}" aria-valuenow="{{ $signups }}">
        <span style="width: {{ $pct }}%"></span>
      </div>
    @endif

    <div class="actions">
      @auth
        @if($isOpenNow)
          @if($alreadyThis)
            @if (Route::has('signups.destroy'))
              <form method="POST" action="{{ route('signups.destroy', $mesa) }}">
                @csrf @method('DELETE')
                <button class="btn danger block" type="submit">{{ $label }}</button>
              </form>
            @endif
          @else
            @if (Route::has('signups.store'))
              <form method="POST" action="{{ route('signups.store', $mesa) }}">
                @csrf
                @if($signedOther)<input type="hidden" name="switch" value="1">@endif
                <button class="btn ok block" type="submit"
                        {{ $signedOther ? 'disabled' : '' }}
                        aria-disabled="{{ $signedOther ? 'true' : 'false' }}"
                        @if($signedOther) title="{{ __('Ya votaste en otra mesa. Retirá tu voto allí para votar aquí.') }}" @endif>
                  {{ $label }}
                </button>
              </form>
            @endif
            @if($signedOther)
              <div class="muted" style="font-size:.85rem">{{ __('Ya votaste en otra mesa. Retirá tu voto allí para votar aquí.') }}</div>
            @endif
          @endif
        @elseif(($mesa->is_open ?? false) && $opensTs > time())
          @if (Route::has('signups.store'))
            <form method="POST" action="{{ route('signups.store', $mesa) }}">
              @csrf
              <button class="btn ok block js-enable-at" type="submit"
                      disabled data-enable-at="{{ $opensTs }}" aria-describedby="hint-{{ $mesa->id }}"
                      title="{{ __('Se habilitará automáticamente a la hora de apertura.') }}"
                      data-autotext="🗳️ Votar">
                {{ __('🗳️ Votar') }}
              </button>
            </form>
            <div id="hint-{{ $mesa->id }}" class="muted" style="font-size:.85rem">
              {{ __('Disponible al abrir.') }}
              <noscript> — {{ __('Requiere JavaScript para habilitarse automáticamente.') }}</noscript>
            </div>
          @endif
        @endif

        <a class="btn block" href="{{ route('mesas.show', $mesa) }}">{{ __('Ver mesa') }}</a>
      @endauth

      @guest
        @if (Route::has('login'))
          <a class="btn block" href="{{ route('login') }}">{{ __('Entrar para votar') }}</a>
        @endif
        <a class="btn block" href="{{ route('mesas.show', $mesa) }}">{{ __('Ver mesa') }}</a>
      @endguest
    </div>

    @if($recent->count())
      <div class="avatars" aria-label="{{ __('Últimos votantes') }}">
        @foreach($recent as $s)
          @php
            $u = $s->user;
            $nameOrUser = $u?->username ?? $u?->name ?? __('Usuario');
            $avatarBase = $u?->avatar_url ?: asset(config('auth.avatars.default','images/avatar-default.svg'));
            $updated = (int) optional($u?->updated_at)->timestamp;
            $src = $avatarBase . ($updated ? ('?v='.$updated) : '');
            $profileUrl = Route::has('profile.show') && $u ? route('profile.show', $u) : '#';
          @endphp
          <a href="{{ $profileUrl }}" @if($profileUrl==='#') aria-disabled="true" @endif>
            <img src="{{ $src }}" alt="{{ $nameOrUser }}" title="{{ $nameOrUser }}"
                 loading="lazy" decoding="async" width="28" height="28">
          </a>
        @endforeach
        @if($extra > 0)
          <span class="more" aria-label="{{ __(':n más', ['n' => $extra]) }}">+{{ $extra }}</span>
        @endif
      </div>
    @endif
  </div>
</article>

@once
@push('head')
<style>
/* Card mesas (solo claro) */
.mesa-card .state{position:absolute;top:8px;left:8px;padding:4px 8px;border-radius:999px;font-size:.75rem;color:#fff;background:#111;opacity:.9}
.mesa-card .state.open{background:#2e7d32}
.mesa-card .state.closed{background:#6b7280}
.mesa-card .bar{height:6px;background:#f1f1f1;border-radius:999px;overflow:hidden;margin:.5rem 0}
.mesa-card .bar>span{display:block;height:100%;background:#3b82f6}
</style>
@endpush

@push('scripts')
<script>
(function(){
  function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn,{once:true});} else { fn(); } }

  ready(function(){
    // Corrige desfase usando meta del layout
    const meta = document.querySelector('meta[name="server-now-ms"]');
    const serverNowMs = meta ? parseInt(meta.content,10) : Date.now();
    const skew = serverNowMs - Date.now();
    const nowMs = () => Date.now() + skew;

    function initCountdown(root){
      const t = root.querySelector('[data-countdown-ts]');
      const label = root.querySelector('[data-countdown-label]');
      if(!t || !label) return;
      let stopped = false;
      function tick(){
        if(stopped) return;
        const opensSec = parseInt(t.dataset.countdownTs,10) || 0;
        if(!opensSec) return;
        const diff = Math.max(0, (opensSec*1000) - nowMs());
        if(diff === 0){ label.textContent = ' · Abriendo…'; return; }
        const s = Math.floor(diff/1000);
        const h = String(Math.floor(s/3600)).padStart(2,'0');
        const m = String(Math.floor((s%3600)/60)).padStart(2,'0');
        const ss = String(s%60).padStart(2,'0');
        label.textContent = ` · Abre en ${h}:${m}:${ss}`;
      }
      tick();
      const id = setInterval(tick, 1000);
      root.addEventListener('mesa:enabled', ()=>{ stopped=true; clearInterval(id); label.textContent=' · Abierta'; });
      document.addEventListener('visibilitychange', ()=>{ if(document.visibilityState==='visible') tick(); });
    }

    function initEnableAt(root){
      const btn = root.querySelector('.js-enable-at[data-enable-at]');
      if(!btn) return;
      const opensSec = parseInt(btn.dataset.enableAt,10) || 0;
      if(!opensSec) return;
      const check = ()=>{
        if(nowMs() >= opensSec*1000){
          btn.disabled = false;
          btn.setAttribute('aria-disabled','false');
          const t = btn.dataset.autotext || btn.textContent;
          btn.textContent = t;
          root.dispatchEvent(new CustomEvent('mesa:enabled'));
          document.removeEventListener('visibilitychange', onVis);
          clearInterval(idSlow);
        }
      };
      const delta = opensSec*1000 - nowMs();
      if(delta > 0) setTimeout(check, delta);
      const onVis = ()=>{ if(document.visibilityState==='visible') check(); };
      document.addEventListener('visibilitychange', onVis);
      const idSlow = setInterval(check, 15000);
      check();
    }

    function initCard(root){
      initEnableAt(root);
      initCountdown(root);
      root.querySelectorAll('form').forEach(f=>{
        f.addEventListener('submit', ()=>{ const b=f.querySelector('button'); if(b){ b.disabled=true; b.classList.add('is-disabled'); } }, {once:true});
      });
    }

    document.querySelectorAll('.mesa-card').forEach(initCard);
  });
})();
</script>
@endpush
@endonce
