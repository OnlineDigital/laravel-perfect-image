<?php

namespace OnlineDigital\PerfectImage;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string render(string $id, string $src, ?array $sizes = null)
 * @method static array getResolutions(string $id)
 * @method static void saveResolutions(string $id, array $resolutions)
 * @method static string getJavaScript()
 *
 * @see \OnlineDigital\PerfectImage\ImageManager
 */
class PerfectImage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ImageManager::class;
    }
}
