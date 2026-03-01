<?php

namespace OnlineDigital\PerfectImage;

use OnlineDigital\PerfectImage\Contracts\Driver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class ImageManager
{
    protected Driver $driver;
    protected int $maxResolutions;
    protected int $minWidthDiff;

    public function __construct(Driver $driver, int $maxResolutions = 5, int $minWidthDiff = 50)
    {
        $this->driver = $driver;
        $this->maxResolutions = $maxResolutions;
        $this->minWidthDiff = $minWidthDiff;
    }

    /**
     * Render attributes for an image
     */
    public function render(string $id, string $src, ?array $sizes = null): string
    {
        $resolutions = $this->getResolutions($id);
        
        if (!empty($resolutions)) {
            return $this->renderSrcset($id, $src, $resolutions);
        }
        
        return $this->renderTracking($id, $src);
    }

    /**
     * Get cached resolutions for an image
     */
    public function getResolutions(string $id): array
    {
        $cacheKey = "perfect_image_{$id}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Render srcset attribute from cached resolutions
     */
    protected function renderSrcset(string $id, string $src, array $resolutions): string
    {
        $srcsetParts = [];
        
        foreach ($resolutions as [$width, $height]) {
            $url = $this->driver->generateUrl($src, $width, $height);
            $srcsetParts[] = "{$url} {$width}w";
        }
        
        $srcset = implode(', ', $srcsetParts);
        
        // Get smallest as src
        $smallest = $resolutions[0];
        $fallbackUrl = $this->driver->generateUrl($src, $smallest[0], $smallest[1] ?? null);
        
        return "src=\"{$fallbackUrl}\" srcset=\"{$srcset}\" sizes=\"100vw\"";
    }

    /**
     * Render tracking attributes when no resolutions cached
     */
    protected function renderTracking(string $id, string $src): string
    {
        $route = route('_perfect_image_observer');
        $js = $this->getJavaScript();
        
        // Store JS in session to render once
        if (!session()->has('perfect_image_js_injected')) {
            session()->put('perfect_image_js_injected', true);
            view()->share('perfect_image_js', $js);
        }
        
        return "data-perfect-image-id=\"{$id}\" "
             . "data-perfect-image-src=\"{$src}\" "
             . "data-perfect-image-endpoint=\"{$route}\"";
    }

    /**
     * Handle the observer endpoint request
     */
    public function handleObserverRequest(): array
    {
        $request = request();
        
        $id = $request->input('id');
        $src = $request->input('src');
        $resolutions = $request->input('resolutions', []);
        
        if (!$id || !$src) {
            return ['success' => false, 'message' => 'Missing id or src'];
        }
        
        $this->saveResolutions($id, $resolutions);
        
        return ['success' => true];
    }

    /**
     * Save resolutions, keeping only the most common ones
     */
    public function saveResolutions(string $id, array $newResolutions): void
    {
        if (empty($newResolutions)) {
            return;
        }
        
        $existing = $this->getResolutions($id);
        
        // Count occurrences of each width
        $widthCounts = [];
        foreach (array_merge($existing, $newResolutions) as [$width, $height]) {
            // Round to nearest 50 to group similar widths
            $rounded = round($width / $this->minWidthDiff) * $this->minWidthDiff;
            
            if (!isset($widthCounts[$rounded])) {
                $widthCounts[$rounded] = ['count' => 0, 'width' => $width, 'height' => $height];
            }
            $widthCounts[$rounded]['count']++;
        }
        
        // Sort by count (most common first), then by width (smallest first)
        usort($widthCounts, function ($a, $b) {
            if ($b['count'] !== $a['count']) {
                return $b['count'] - $a['count'];
            }
            return $a['width'] - $b['width'];
        });
        
        // Take top N resolutions
        $topResolutions = array_slice($widthCounts, 0, $this->maxResolutions);
        
        // Format as array of [width, height]
        $final = array_map(fn($r) => [$r['width'], $r['height']], $topResolutions);
        
        // Sort by width ascending for srcset
        usort($final, fn($a, $b) => $a[0] - $b[0]);
        
        $cacheKey = "perfect_image_{$id}";
        Cache::put($cacheKey, $final, now()->addDays(30));
    }

    /**
     * Generate the JavaScript for automatic injection
     */
    public static function getJavaScript(): string
    {
        return <<<'JS'
(function() {
    const PerfectImage = {
        observed: new Set(),
        debounceTimer: null,
        
        init: function() {
            this.observePageLoad();
            this.observeResize();
        },
        
        observePageLoad: function() {
            const images = document.querySelectorAll('[data-perfect-image-id]');
            images.forEach(img => this.trackImage(img));
        },
        
        trackImage: function(img) {
            const id = img.dataset.perfectImageId;
            const src = img.dataset.perfectImageSrc;
            const endpoint = img.dataset.perfectImageEndpoint;
            
            if (this.observed.has(id)) return;
            this.observed.add(id);
            
            // Replace with tracking attributes
            img.removeAttribute('data-perfect-image-id');
            img.removeAttribute('data-perfect-image-src');
            img.removeAttribute('data-perfect-image-endpoint');
            
            // Add onload
            img.addEventListener('load', () => {
                this.collectDimensions(id, src, endpoint, img);
            });
            
            // If already loaded
            if (img.complete) {
                this.collectDimensions(id, src, endpoint, img);
            }
        },
        
        collectDimensions: function(id, src, endpoint, img) {
            const width = Math.round(img.width || img.clientWidth);
            const height = Math.round(img.height || img.clientHeight);
            
            if (!this.observed.has(id + '_dims')) {
                this.observed.add(id + '_dims');
                PerfectImage.sendDimensions(id, src, endpoint, [[width, height]]);
            }
        },
        
        observeResize: function() {
            window.addEventListener('resize', () => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    const images = document.querySelectorAll('[data-perfect-image-id]');
                    images.forEach(img => this.trackImage(img));
                }, 500);
            });
        },
        
        sendDimensions: function(id, src, endpoint, extraDims) {
            const key = 'perfect_image_res_' + id;
            const stored = JSON.parse(localStorage.getItem(key) || '[]');
            
            // Add new dimensions
            extraDims.forEach(d => {
                if (!stored.some(s => s[0] === d[0] && s[1] === d[1])) {
                    stored.push(d);
                }
            });
            
            // Keep max 20 to avoid localStorage overflow
            if (stored.length > 20) {
                stored.splice(0, stored.length - 20);
            }
            
            localStorage.setItem(key, JSON.stringify(stored));
            
            // Send to server
            if (endpoint && stored.length >= 3) {
                fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify({
                        id: id,
                        src: src,
                        resolutions: stored
                    })
                }).then(() => {
                    localStorage.removeItem(key);
                    // Reload to get srcset
                    location.reload();
                }).catch(() => {});
            }
        }
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => PerfectImage.init());
    } else {
        PerfectImage.init();
    }
    
    window.PerfectImage = PerfectImage;
})();
JS;
    }
}
