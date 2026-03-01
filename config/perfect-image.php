<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | The cache driver to use for storing resolution data.
    | Default: file
    |
    */
    'cache_driver' => env('PERFECT_IMAGE_CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Resolutions
    |--------------------------------------------------------------------------
    |
    | How many of the most common resolutions to keep per image.
    |
    */
    'max_resolutions' => 5,

    /*
    |--------------------------------------------------------------------------
    | Minimum Width Difference
    |--------------------------------------------------------------------------
    |
    | Minimum width difference to count as a new resolution.
    | This prevents storing very similar widths.
    |
    */
    'min_width_diff' => 50,

    /*
    |--------------------------------------------------------------------------
    | Route Name
    |--------------------------------------------------------------------------
    |
    | The route name for the observer endpoint.
    |
    */
    'route_name' => '_perfect_image_observer',

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default driver for URL generation.
    | Options: url, cloudinary, imgix, etc.
    |
    */
    'driver' => env('PERFECT_IMAGE_DRIVER', 'url'),

    /*
    |--------------------------------------------------------------------------
    | Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | Driver-specific configuration for URL generation.
    |
    */
    'drivers' => [
        'url' => [
            'param_format' => '{url}?w={width}&h={height}',
        ],
        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
        ],
        'imgix' => [
            'domain' => env('IMGIX_DOMAIN', ''),
        ],
    ],
];
