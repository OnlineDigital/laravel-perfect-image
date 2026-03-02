<?php

namespace OnlineDigital\PerfectImage\Drivers;

use OnlineDigital\PerfectImage\Contracts\Driver;

class UrlDriver implements Driver
{
    public function generateUrl(string $url, int $width, ?int $height = null): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $format = config('perfect-image.drivers.url.param_format', '{url}?w={width}&h={height}');
        $callback = config('perfect-image.drivers.url.url_before_callback');
        if (is_callable($callback)) {
            $url = $callback($url);
        }
        
        $result = str_replace('{url}', $url, $format);
        $result = str_replace('{width}', (string) $width, $result);
        $result = str_replace('{height}', $height !== null ? (string) $height : '', $result);
        
        // Clean up empty query params
        $result = preg_replace('/\?w=\d+&h=$/', '?w=' . $width, $result);
        $result = preg_replace('/\?w=\d+\?/', '?', $result);
        
        return $result;
    }
}
