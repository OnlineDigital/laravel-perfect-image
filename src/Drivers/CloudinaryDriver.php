<?php

namespace OnlineDigital\PerfectImage\Drivers;

use OnlineDigital\PerfectImage\Contracts\Driver;

class CloudinaryDriver implements Driver
{
    public function generateUrl(string $url, int $width, ?int $height = null): string
    {
        $cloudName = config('perfect-image.drivers.cloudinary.cloud_name', '');
        
        // If it's already a cloudinary URL, transform it
        if (str_contains($url, 'cloudinary.com')) {
            $transformation = "w_{$width}";
            if ($height !== null) {
                $transformation .= ",h_{$height}";
            }
            
            return preg_replace(
                '/\/image\/upload\//',
                '/image/upload/' . $transformation . '/',
                $url
            );
        }
        
        // Otherwise, construct a cloudinary URL
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        
        return "https://res.cloudinary.com/{$cloudName}/image/upload/w_{$width}" 
            . ($height ? ",h_{$height}" : '') 
            . "/{$path}";
    }
}
