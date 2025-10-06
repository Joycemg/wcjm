<?php

return [
    'autogenerate_username' => true,
    'autogenerate_username_max_attempts' => 50,
    'reserved_usernames' => [
        // acá podés agregar más, ej.: 'club', 'torneo'
    ],
    'avatar_disk' => env('USERS_AVATAR_DISK', 'public'), // o 'b2' si usás Backblaze
    'avatar_generate_thumbs' => true,
    'avatar_thumb_sizes' => [512, 256, 128],



];
