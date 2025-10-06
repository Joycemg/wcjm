@props(['status'])

@if ($status)
    <div {{ $attributes->class(['form-status']) }}>
        {{ $status }}
    </div>
@endif
