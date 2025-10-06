@props(['messages'])

@if ($messages)
    <ul {{ $attributes->class(['form-error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
