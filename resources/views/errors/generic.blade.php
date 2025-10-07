@extends('layouts.app')

@section('title', ($code ?? 500) . ' · ' . ($message ?? __('Error'))) 

@section('content')
    <section class="card" style="background:#fff;border:1px solid #e5e7eb;border-radius:.75rem;padding:1.5rem;max-width:32rem;margin:2rem auto">
        <h1 style="margin-top:0;margin-bottom:.75rem;font-size:1.75rem;font-weight:700;color:#7b2d26">
            {{ $code ?? 500 }}
        </h1>
        <p style="margin-bottom:1.5rem;color:#374151">
            {{ $message ?? __('Ocurrió un error inesperado.') }}
        </p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
               class="btn"
               style="display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:.6rem;border:1px solid #e5e7eb;text-decoration:none;color:#1f2937">
                ⬅️ {{ __('Volver') }}
            </a>
            <a href="{{ url('/') }}"
               class="btn"
               style="display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border-radius:.6rem;border:1px solid #e5e7eb;text-decoration:none;color:#1f2937">
                🏠 {{ __('Ir al inicio') }}
            </a>
        </div>
    </section>
@endsection
