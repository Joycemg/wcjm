<button {{ $attributes->merge(['type' => 'submit'])->class(['btn', 'ok']) }}>
    {{ $slot }}
</button>
