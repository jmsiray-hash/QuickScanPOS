<?php

return [
    'driver' => Intervention\Image\Drivers\Gd\Driver::class,
    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => true,
        'blendingColor' => 'ffffff',
        'strip' => false,
    ],
];
