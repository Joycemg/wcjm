@props(['active'])

@php
$classes = ($active ?? false)
            ? 'btn active'
            : 'btn';
@endphp

<a {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</a>
