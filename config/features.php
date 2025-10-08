<?php

return [
    'honor' => [
        'enabled' => (bool) env('FEATURES_HONOR_ENABLED', true),
        'ranking_public' => (bool) env('FEATURES_HONOR_RANKING_PUBLIC', true),
        'decay_inactivity' => (bool) env('FEATURES_HONOR_DECAY_INACTIVITY', true),
    ],
];
