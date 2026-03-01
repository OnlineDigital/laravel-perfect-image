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
    protected ?string $cspNonce = null;

    public function __construct(Driver $driver, int $maxResolutions = 5, int $minWidthDiff = 50)
    {
        $this->driver = $driver;
        $this->maxResolutions = $maxResolutions;
        $this->minWidthDiff = $minWidthDiff;
    }

    public function setCspNonce(?string $nonce): void
    {
        $this->cspNonce = $nonce ?: null;
    }

    public function getCspNonce(): ?string
    {
        return $this->cspNonce;
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
        $js = $this->getJavaScript($route);
        
        // Store JS in session to render once
        if (!session()->has('perfect_image_js_injected')) {
            session()->put('perfect_image_js_injected', true);
            view()->share('perfect_image_js', $js);
        }
        
        return "data-perfect-image-id=\"{$id}\" "
             . "data-perfect-image-src=\"{$src}\"";
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
        $minWidth = 300;
        $maxWidth = 1800;
        
        $widths = [];
        $ratios = [];
        foreach (array_merge($existing, $newResolutions) as $resolution) {
            $width = $resolution[0] ?? null;
            $height = $resolution[1] ?? null;
            if (!$width || !$height) {
                continue;
            }
            if ($width < $minWidth || $width > $maxWidth) {
                continue;
            }
            $widths[] = $width;
            $ratios[] = $height / $width;
        }

        if (empty($widths)) {
            return;
        }

        sort($widths);
        sort($ratios);
        $minObserved = $widths[0];
        $maxObserved = $widths[count($widths) - 1];
        $minObserved = max($minWidth, $minObserved);
        $maxObserved = min($maxWidth, $maxObserved);

        if ($minObserved > $maxObserved) {
            return;
        }

        $ratioIndex = (int) floor((count($ratios) - 1) / 2);
        $ratio = $ratios[$ratioIndex];
        if ($ratio <= 0) {
            return;
        }

        $final = [];
        for ($w = $minObserved; $w <= $maxObserved; $w += $this->minWidthDiff) {
            $h = (int) round($w * $ratio);
            if ($h <= 0) {
                continue;
            }
            $final[] = [$w, $h];
        }
        
        // Sort by width ascending for srcset
        usort($final, fn($a, $b) => $a[0] - $b[0]);
        
        $cacheKey = "perfect_image_{$id}";
        Cache::put($cacheKey, $final, now()->addDays(30));
    }

    /**
     * Generate the JavaScript for automatic injection
     */
    public static function getJavaScript(?string $endpoint = null): string
    {
        $endpoint = $endpoint ?: route(config('perfect-image.route_name', '_perfect_image_observer'));
        $endpointJson = json_encode($endpoint, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return <<<JS
(function() {
    const endpoint = {$endpointJson};
    const MIN_WIDTH = 300;
    const MAX_WIDTH = 1800;
    const BUCKET_SIZE = 50;
    const PerfectImage = {
        observed: new Set(),
        trackedImages: new Map(),
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
            const endpointUrl = endpoint;
            
            if (this.observed.has(id)) {
                this.trackedImages.set(id, { img, src, endpointUrl });
                return;
            }
            this.observed.add(id);
            this.trackedImages.set(id, { img, src, endpointUrl });
            
            return;
        },
        
        collectDimensions: function(id, src, endpoint, img) {
            const width = Math.round(img.width || img.clientWidth);
            const height = Math.round(img.height || img.clientHeight);
            const screenWidth = Math.round(
                window.visualViewport?.width ||
                window.innerWidth ||
                document.documentElement.clientWidth ||
                window.screen?.width ||
                0
            );
            
            if (!width || !height || !screenWidth) return;
            PerfectImage.sendDimensions(id, src, endpoint, [[width, height, screenWidth]]);
        },
        
        collectAllDimensions: function() {
            this.trackedImages.forEach(({ img, src, endpointUrl }, id) => {
                this.collectDimensions(id, src, endpointUrl, img);
            });
        },
        
        observeResize: function() {
            window.addEventListener('resize', () => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.observePageLoad();
                    this.collectAllDimensions();
                }, 300);
            });
        },
        
        hasCoverage: function(stored) {
            const buckets = new Set();
            stored.forEach(d => {
                const screenWidth = d[2];
                if (!screenWidth) return;
                const clamped = Math.max(MIN_WIDTH, Math.min(MAX_WIDTH, screenWidth));
                const bucket = Math.floor((clamped - MIN_WIDTH) / BUCKET_SIZE);
                buckets.add(bucket);
            });
            const totalBuckets = Math.floor((MAX_WIDTH - MIN_WIDTH) / BUCKET_SIZE) + 1;
            return buckets.size >= totalBuckets;
        },
        
        sendDimensions: function(id, src, endpoint, extraDims) {
            const key = 'perfect_image_res_' + id;
            const stored = JSON.parse(localStorage.getItem(key) || '[]');
            
            // Add new dimensions
            extraDims.forEach(d => {
                const screenWidth = d[2];
                if (!screenWidth) return;
                if (!stored.some(s => s[0] === d[0] && s[1] === d[1] && s[2] === d[2])) {
                    stored.push(d);
                }
            });
            
            localStorage.setItem(key, JSON.stringify(stored));
            
            // Send to server
            if (endpoint && (this.hasCoverage(stored) || stored.length > 40)) {
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
