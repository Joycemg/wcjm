<x-guest-layout>
    <header class="auth-header">
        <h1 class="auth-title">{{ __('Verificá tu email') }}</h1>
        <p class="form-hint">{{ __('Te enviamos un enlace para confirmar tu correo. Si no llegó, podés pedir otro a continuación.') }}</p>
    </header>

    @if (session('status') == 'verification-link-sent')
        <div class="form-alert form-alert-info" role="status" aria-live="polite">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="form-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="form-link" style="background:none;border:0;padding:0;cursor:pointer">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
