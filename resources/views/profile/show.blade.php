{{-- resources/views/profile/show.blade.php --}}
@extends('layouts.app')
@section('title', ($user->name ?? $user->username ?? __('Perfil')) . ' · ' . config('app.name', 'La Taberna'))

@push('head')
    @php
        $name = $user->name ?: ($user->username ?: __('Usuario'));
        $profileUrl = Route::has('profile.show')
            ? (isset($user->profile_param) ? route('profile.show', $user->profile_param) : route('profile.show', $user))
            : url()->current();

        // Avatar con bust de caché
        $defaultAvatar = asset(config('auth.avatars.default', 'images/avatar-default.svg'));
        $baseAvatar = $user->avatar_url ?? $defaultAvatar;
        $ver = optional($user->updated_at)->timestamp;
        $avatar = $baseAvatar . ($ver ? ('?v=' . $ver) : '');

        // Descripción corta para compartir
        $desc = trim($user->bio ?? '') !== '' ? Str::limit($user->bio, 160, '…') : __('Perfil de :name', ['name' => $name]);
    @endphp

    {{-- Canonical --}}
    <link rel="canonical"
          href="{{ $profileUrl }}" />

    {{-- Open Graph / Twitter Card --}}
    <meta property="og:type"
          content="profile">
    <meta property="og:title"
          content="{{ $name }} · {{ config('app.name', 'La Taberna') }}">
    <meta property="og:description"
          content="{{ $desc }}">
    <meta property="og:image"
          content="{{ $avatar }}">
    <meta property="og:url"
          content="{{ $profileUrl }}">
    <meta name="twitter:card"
          content="summary">
    <meta name="twitter:title"
          content="{{ $name }} · {{ config('app.name', 'La Taberna') }}">
    <meta name="twitter:description"
          content="{{ $desc }}">
    <meta name="twitter:image"
          content="{{ $avatar }}">

    <style>
        .profile-wrap {
            max-width: 760px;
            margin-inline: auto
        }

        .profile-hero {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            flex-wrap: wrap
        }

        .profile-ava {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            border: 2px solid var(--line);
            object-fit: cover;
            background: #f6f6f6
        }

        .profile-title {
            color: var(--maroon);
            margin: .2rem 0
        }

        .profile-meta {
            margin-top: .4rem;
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
            align-items: center
        }

        .profile-meta .sep {
            opacity: .6
        }

        .profile-bio {
            white-space: pre-line
        }
    </style>
@endpush

@section('content')
    @php
        $name = $user->name ?: ($user->username ?: __('Usuario'));
        $username = $user->username ?: null;

        // rol
        $roleKey = $user->role ?? 'usuario';
        $roleLabel = __($roleKey);

        // fechas
        $created = $user->created_at;
        $joinedIso = $created?->toAtomString();
        $joinedLbl = $created?->isoFormat('LL');
        $joinedRel = $created?->diffForHumans(['parts' => 2]);

        // avatar con fallback y bust de caché
        $defaultAvatar = asset(config('auth.avatars.default', 'images/avatar-default.svg'));
        $baseAvatar = $user->avatar_url ?? $defaultAvatar;
        $ver = optional($user->updated_at)->timestamp;
        $avatar = $baseAvatar . ($ver ? ('?v=' . $ver) : '');
    @endphp

    <article class="card profile-wrap"
             itemscope
             itemtype="https://schema.org/Person"
             aria-labelledby="profile-title">
        <header class="profile-hero">
            <img class="profile-ava"
                 src="{{ $avatar }}"
                 alt="{{ __('Avatar de :name', ['name' => $name]) }}"
                 width="96"
                 height="96"
                 loading="lazy"
                 decoding="async"
                 itemprop="image"
                 onerror="this.onerror=null;this.src='{{ $defaultAvatar }}'">

            <div style="flex:1;min-width:240px">
                <h1 id="profile-title"
                    class="profile-title"
                    itemprop="name">{{ $name }}</h1>

                @if($username)
                    <div class="muted"
                         itemprop="alternateName">@ {{ $username }}</div>
                @endif

                <div class="profile-meta muted">
                    <span>{{ __('Rol') }}: <strong>{{ $roleLabel }}</strong></span>
                    @if($joinedLbl)
                        <span class="sep"
                              aria-hidden="true">·</span>
                        <span>
                            {{ __('Miembro desde') }}:
                            <time datetime="{{ $joinedIso }}"
                                  title="{{ $joinedRel }}">{{ $joinedLbl }}</time>
                        </span>
                    @endif
                </div>
            </div>

            @can('update', $user)
                @if(Route::has('profile.edit'))
                    <a class="btn"
                       href="{{ route('profile.edit') }}">{{ __('Editar perfil') }}</a>
                @endif
            @endcan
        </header>

        @if(filled($user->bio))
            <div class="divider"></div>
            <p class="profile-bio"
               itemprop="description">{{ $user->bio }}</p>
        @endif
    </article>
@endsection