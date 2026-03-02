<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | The cache driver to use for storing resolution data.
    | Options: file, storage_file
    | Default: file
    |
    | If 'storage_file' is used, resolutions are stored in a JSON file
    | at storage_path('app/perfect_image_resolutions.json').
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
    | Resolution Limits
    |--------------------------------------------------------------------------
    |
    | The minimum and maximum widths to store.
    | Images smaller than min_width or larger than max_width will be ignored.
    |
    */
    'min_width' => 100,
    'max_width' => 3000,

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
            'url_before_callback' => function ($url) {
                return $url;
            },
        ],
        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
        ],
        'imgix' => [
            'domain' => env('IMGIX_DOMAIN', ''),
        ],
    ],
];
