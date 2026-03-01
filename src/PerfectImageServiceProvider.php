<?php

namespace OnlineDigital\PerfectImage;

use OnlineDigital\PerfectImage\Contracts\Driver;
use OnlineDigital\PerfectImage\Drivers\UrlDriver;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PerfectImageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/perfect-image.php',
            'perfect-image'
        );

        $this->app->singleton(Driver::class, function ($app) {
            $driverName = config('perfect-image.driver', 'url');
            
            return match ($driverName) {
                'url' => new UrlDriver(),
                'cloudinary' => new Drivers\CloudinaryDriver(),
                'imgix' => new Drivers\ImgixDriver(),
                default => new UrlDriver(),
            };
        });

        $this->app->singleton(ImageManager::class, function ($app) {
            return new ImageManager(
                $app->make(Driver::class),
                config('perfect-image.max_resolutions', 5),
                config('perfect-image.min_width_diff', 50)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/perfect-image.php' => config_path('perfect-image.php'),
        ], 'perfect-image-config');

        $this->registerBladeDirective();
        $this->registerRoute();
        $this->registerMiddleware();
    }

    protected function registerBladeDirective(): void
    {
        \Illuminate\Support\Facades\Blade::directive('perfect_image_srcset', function ($expression) {
            // Parse: 'unique-key', $imagepath, ['sizes']
            $parts = array_map(fn($p) => trim($p), explode(',', $expression));
            $id = $parts[0] ?? '""';
            $src = $parts[1] ?? '""';
            $sizes = $parts[2] ?? 'null';

            return "<?php echo \\AndreiTelteu\\PerfectImage\\PerfectImage::render({$id}, {$src}, {$sizes}); ?>";
        });
    }

    protected function registerRoute(): void
    {
        $routeName = config('perfect-image.route_name', '_perfect_image_observer');
        
        Route::post($routeName, [\OnlineDigital\PerfectImage\Http\Controllers\PerfectImageController::class, 'observe'])
            ->name($routeName)
            ->middleware('web');
    }
    
    protected function registerMiddleware(): void
    {
        $router = $this->app[Router::class];
        $router->aliasMiddleware('perfect-image', \OnlineDigital\PerfectImage\Http\Middleware\InjectPerfectImageJs::class);
    }
}
