<?php

return [
    // Cache driver to store resolution data
    'cache_driver' => env('PERFECT_IMAGE_CACHE_DRIVER', 'file'),
    
    // How many of the most common resolutions to keep
    'max_resolutions' => 5,
    
    // Minimum width difference to count as a new resolution
    'min_width_diff' => 50,
    
    // The route name for the observer endpoint
    'route_name' => '_perfect_image_observer',
    
    // Default image driver (url, cloudinary, etc.)
    'driver' => env('PERFECT_IMAGE_DRIVER', 'url'),
    
    // Driver-specific configuration
    'drivers' => [
        'url' => [
            // For simple URL manipulation (e.g., adding query params)
            'base_url' => env('APP_URL'),
            'param_format' => '{url}?w={width}&h={height}', // or {url}/{width}x{height}
        ],
        'cloudinary' => [
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'base_url' => 'https://res.cloudinary.com/{cloud_name}/image/upload',
        ],
        // Add more drivers as needed
    ],
];
