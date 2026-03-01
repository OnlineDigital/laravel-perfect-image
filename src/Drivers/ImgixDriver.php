<?php

namespace OnlineDigital\PerfectImage\Drivers;

use OnlineDigital\PerfectImage\Contracts\Driver;

class ImgixDriver implements Driver
{
    public function generateUrl(string $url, int $width, ?int $height = null): string
    {
        $domain = config('perfect-image.drivers.imgix.domain', '');
        
        // If it's already an imgix URL, just add params
        if (str_contains($url, $domain)) {
            $separator = parse_url($url, PHP_QUERY) ? '&' : '?';
            return $url . $separator . "w={$width}" . ($height ? "&h={$height}" : '');
        }
        
        // Otherwise, construct an imgix URL
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        
        return "https://{$domain}/{$path}?w={$width}" . ($height ? "&h={$height}" : '');
    }
}
