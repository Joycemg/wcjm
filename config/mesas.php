<?php

return [
    // Máximo de inscripciones que se devuelven en la lista de espera
    'waitlist_max' => 4,
    'auto_arm_on_future_opens' => false,

    // Disco donde se guardan las imágenes (public, s3, etc.)
    'image_disk' => env('MESAS_IMAGE_DISK', env('FILESYSTEM_DISK', env('FILESYSTEM_DRIVER', 'public'))),

    // Límite de mesas recientes a mostrar en la home (fallback sin partials personalizados)
    'home_latest_limit' => (int) env('MESAS_HOME_LATEST', 4),

    // Cache (en segundos) para el fallback de mesas recientes en la home.
    // En hosting compartido conviene un valor pequeño para evitar recalcular en cada request.
    'home_latest_cache_seconds' => (int) env('MESAS_HOME_LATEST_CACHE', 180),
];