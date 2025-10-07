{{-- resources/views/partials/nav.blade.php --}}
<nav class="card"
     role="navigation"
     aria-label="{{ __('Principal') }}"
     style="padding:.4rem .6rem">
    <div style="max-width:1100px;margin:0 auto;display:flex;align-items:center;gap:.6rem">
        {{-- Logo --}}
        <a href="{{ route('dashboard') }}"
           class="brand"
           style="display:flex;align-items:center;gap:.5rem">
            <img src="{{ asset('images/logo.png') }}"
                 alt="{{ config('app.name', 'La Taberna') }}"
                 width="34"
                 height="34"
                 loading="lazy"
                 decoding="async">
            <span style="font-weight:700;color:var(--maroon)">{{ config('app.name', 'La Taberna') }}</span>
        </a>

        {{-- Links principales (desktop) --}}
        <div class="grow"
             style="flex:1"></div>
        <div class="nav-links"
             style="display:none;gap:.4rem"
             id="nav-primary">
            <a class="btn @if(request()->routeIs('dashboard')) active @endif"
               href="{{ route('dashboard') }}"
               @if(request()->routeIs('dashboard'))
                   aria-current="page"
               @endif>
                {{ __('Panel') }}
            </a>

            @if (Route::has('ranking.honor'))
                <a class="btn @if(request()->routeIs('ranking.*')) active @endif"
                   href="{{ route('ranking.honor') }}"
                   @if(request()->routeIs('ranking.*'))
                       aria-current="page"
                   @endif>
                    {{ __('Ranking de honor') }}
                </a>
            @endif
        </div>

        {{-- Dropdown usuario (desktop) --}}
        <div class="user-dd"
             id="user-dd"
             style="display:none;position:relative">
            @auth
                <button id="user-dd-btn"
                        class="btn"
                        type="button"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-controls="user-dd-menu"
                        style="display:inline-flex;align-items:center;gap:.4rem">
                    <span>{{ Auth::user()->name }}</span>
                    <svg width="14"
                         height="14"
                         viewBox="0 0 20 20"
                         aria-hidden="true">
                        <path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z"
                              fill="currentColor" />
                    </svg>
                </button>

                <div id="user-dd-menu"
                     class="menu"
                     role="menu"
                     hidden
                     style="position:absolute;right:0;top:calc(100% + .35rem);min-width:220px;background:var(--card);border:1px solid var(--line);border-radius: 0;box-shadow:0 8px 24px rgba(0,0,0,.08);padding:.35rem">
                    @if (Route::has('profile.edit'))
                        <a role="menuitem"
                           class="btn"
                           style="width:100%;justify-content:flex-start"
                           href="{{ route('profile.edit') }}">
                            {{ __('Perfil') }}
                        </a>
                    @endif

                    <form method="POST"
                          action="{{ route('logout') }}"
                          style="margin-top:.25rem">
                        @csrf
                        <button role="menuitem"
                                class="btn danger"
                                style="width:100%;justify-content:flex-start"
                                type="submit">
                            {{ __('Salir') }}
                        </button>
                    </form>
                </div>
            @else
                <div style="display:flex;gap:.4rem">
                    @if (Route::has('login'))
                        <a class="btn"
                           href="{{ route('login') }}">{{ __('Entrar') }}</a>
                    @endif
                    @if (Route::has('register'))
                        <a class="btn"
                           href="{{ route('register') }}">{{ __('Registrarse') }}</a>
                    @endif
                </div>
            @endauth
        </div>

        {{-- Hamburguesa (mobile) --}}
        <button id="nav-toggle"
                class="btn"
                type="button"
                aria-controls="nav-mobile"
                aria-expanded="false"
                style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px">
            <svg width="22"
                 height="22"
                 viewBox="0 0 24 24"
                 aria-hidden="true">
                <path id="bar1"
                      d="M4 6h16"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round" />
                <path id="bar2"
                      d="M4 12h16"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round" />
                <path id="bar3"
                      d="M4 18h16"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round" />
            </svg>
            <span class="sr-only">{{ __('Abrir menú') }}</span>
        </button>
    </div>

    {{-- Menú responsive (mobile) --}}
    <div id="nav-mobile"
         class="card"
         hidden
         style="margin-top:.6rem;padding:.6rem;display:grid;gap:.5rem">
        <a class="btn @if(request()->routeIs('dashboard')) active @endif"
           href="{{ route('dashboard') }}"
           @if(request()->routeIs('dashboard'))
               aria-current="page"
           @endif>
            {{ __('Panel') }}
        </a>

        @if (Route::has('ranking.honor'))
            <a class="btn @if(request()->routeIs('ranking.*')) active @endif"
               href="{{ route('ranking.honor') }}"
               @if(request()->routeIs('ranking.*'))
                   aria-current="page"
               @endif>
                {{ __('Ranking de honor') }}
            </a>
        @endif

        @auth
            <div class="muted"
                 style="margin-top:.25rem">
                <div style="font-weight:600">{{ Auth::user()->name }}</div>
                <div style="font-size:.9rem">{{ Auth::user()->email }}</div>
            </div>

            @if (Route::has('profile.edit'))
                <a class="btn"
                   href="{{ route('profile.edit') }}">{{ __('Perfil') }}</a>
            @endif

            <form method="POST"
                  action="{{ route('logout') }}">
                @csrf
                <button class="btn danger"
                        type="submit">{{ __('Salir') }}</button>
            </form>
        @else
            @if (Route::has('login'))
                <a class="btn"
                   href="{{ route('login') }}">{{ __('Entrar') }}</a>
            @endif
            @if (Route::has('register'))
                <a class="btn"
                   href="{{ route('register') }}">{{ __('Registrarse') }}</a>
            @endif
        @endauth
    </div>
</nav>

@push('head')
    <style>
        /* Mostrar/ocultar bloques según ancho */
        @media (min-width: 640px) {

            #nav-primary,
            #user-dd {
                display: flex !important;
            }

            #nav-toggle,
            #nav-mobile {
                display: none !important;
            }
        }

        .sr-only {
            position: absolute !important;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Pequeño hover para items del dropdown */
        #user-dd-menu .btn {
            background: #fff;
        }

        #user-dd-menu .btn:hover {
            background: #f7f2ea;
        }

        @media (prefers-color-scheme: dark) {
            #user-dd-menu .btn {
                background: #151618;
            }

            #user-dd-menu .btn:hover {
                background: #1d2023;
            }
        }
    </style>
@endpush

@push('scripts')
    @once
        <script>
            (function () {
                const sel = (s, r = document) => r.querySelector(s);

                // Dropdown usuario
                const ddBtn = sel('#user-dd-btn');
                const ddMenu = sel('#user-dd-menu');

                function closeDd() {
                    if (!ddMenu) return;
                    ddMenu.hidden = true;
                    ddBtn?.setAttribute('aria-expanded', 'false');
                }
                function openDd() {
                    if (!ddMenu) return;
                    ddMenu.hidden = false;
                    ddBtn?.setAttribute('aria-expanded', 'true');
                }

                ddBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (ddMenu.hidden) openDd(); else closeDd();
                }, { passive: false });

                document.addEventListener('click', (e) => {
                    if (!ddMenu || ddMenu.hidden) return;
                    if (e.target === ddBtn || ddBtn?.contains(e.target)) return;
                    if (ddMenu.contains(e.target)) return;
                    closeDd();
                }, { passive: true });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeDd();
                });

                // Hamburguesa
                const toggler = sel('#nav-toggle');
                const mobile = sel('#nav-mobile');

                function setMobile(open) {
                    if (!mobile || !toggler) return;
                    mobile.hidden = !open;
                    toggler.setAttribute('aria-expanded', open ? 'true' : 'false');
                }

                toggler?.addEventListener('click', (e) => {
                    e.preventDefault();
                    setMobile(mobile.hidden);
                }, { passive: false });

                // Cerrar mobile al navegar
                mobile?.addEventListener('click', (e) => {
                    const t = e.target;
                    if (t.tagName === 'A' || (t.tagName === 'BUTTON' && t.type === 'submit')) {
                        setMobile(false);
                    }
                });

                // Cerrar dropdown si cambia el tamaño a mobile
                window.addEventListener('resize', () => { closeDd(); }, { passive: true });
            })();
        </script>
    @endonce
@endpush