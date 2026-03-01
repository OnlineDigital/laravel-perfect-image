# Laravel Perfect Image

Automatic responsive image optimization for Laravel. Detects rendered image dimensions, caches the most common resolutions, and generates srcset attributes automatically.

## Features

- Automatic detection of rendered image dimensions via JavaScript
- Caches the most common resolutions per image
- Driver-based URL generation (supports multiple image services)
- Blade directive for easy usage
- Lazy resize tracking

## Installation

```bash
composer require OnlineDigital/laravel-perfect-image
```

### Add Middleware (Optional - for automatic JS injection)

Add to your `bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'perfect-image' => \OnlineDigital\PerfectImage\Http\Middleware\InjectPerfectImageJs::class,
        ]);
    })
    ->create();
```

Or apply to specific routes:

```php
Route::get('/home', [HomeController::class, 'index'])->middleware('perfect-image');
```

### Publish Config

```bash
php artisan vendor:publish --provider="OnlineDigital\\PerfectImage\\PerfectImageServiceProvider" --tag="perfect-image-config"
```

### Config Options

```php
// config/perfect-image.php
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
```

## Usage

### Basic Usage

In your Blade templates:

```blade
<img {{ perfect_image_srcset('unique-key', $imagepath) }}>
```

Example:

```blade
<img {{ perfect_image_srcset('hero-image', asset('images/hero.jpg')) }}>
```

This generates:

```html
<!-- If resolutions are cached -->
<img srcset="https://example.com/images/hero.jpg?w=320 320w, https://example.com/images/hero.jpg?w=640 640w, ...">

<!-- If no cached resolutions -->
<img 
    data-perfect-image-id="hero-image" 
    data-perfect-image-src="https://example.com/images/hero.jpg"
    onload="PerfectImage.observe(this)">
```

### Custom Sizes

```blade
<img {{ perfect_image_srcset('product-image', $product->image, ['640', '1024', '1280']) }}>
```

### JavaScript

The package automatically injects the required JavaScript. Make sure you have jQuery or the script will be included automatically.

## Creating Custom Drivers

Create a class that implements `OnlineDigital\PerfectImage\Contracts\Driver`:

```php
<?php

namespace App\Drivers;

use OnlineDigital\PerfectImage\Contracts\Driver;

class MyCustomDriver implements Driver
{
    public function generateUrl(string $url, int $width, ?int $height = null): string
    {
        // Generate the URL for the resized image
        return "https://my-cdn.com/{$width}x{$height}/{$url}";
    }
}
```

Register it in a service provider:

```php
$this->app->bind(\OnlineDigital\PerfectImage\Contracts\Driver::class, MyCustomDriver::class);
```

## How It Works

1. **First Visit**: If no cached resolutions exist for an image, the Blade directive adds data attributes and an onload handler
2. **JavaScript Tracking**: When the page loads, JS detects all tracked images' rendered dimensions
3. **Dimension Collection**: After window resize (debounced), dimensions are sent to the `_perfect_image_observer` endpoint
4. **Caching**: The server saves the most common resolutions (configurable count)
5. **Subsequent Visits**: srcset is generated from cached resolutions

## Route

The package automatically registers the observer route:

```
POST _perfect_image_observer
```

Request body:
```json
{
    "id": "unique-image-key",
    "src": "https://example.com/image.jpg",
    "resolutions": [[320, 240], [640, 480], [1024, 768]]
}
```

## License

MIT
