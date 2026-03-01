<?php

namespace OnlineDigital\PerfectImage\Contracts;

interface Driver
{
    /**
     * Generate URL for resized image
     * @param     * 
 string $url Original image URL
     * @param int $width Target width
     * @param int|null $height Target height (optional)
     * @return string Generated URL
     */
    public function generateUrl(string $url, int $width, ?int $height = null): string;
}
