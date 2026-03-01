<script>
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
            
            // Add onload
            img.addEventListener('load', () => {
                this.collectDimensions(id, src, endpoint, img);
            });
            
            // If already loaded
            if (img.complete && img.naturalWidth > 0) {
                this.collectDimensions(id, src, endpoint, img);
            }
        },
        
        collectDimensions: function(id, src, endpoint, img) {
            const width = Math.round(img.width || img.clientWidth);
            const height = Math.round(img.height || img.clientHeight);
            
            if (width === 0 || height === 0) return;
            
            const key = 'perfect_image_res_' + id;
            const stored = JSON.parse(localStorage.getItem(key) || '[]');
            
            // Add if not exists
            if (!stored.some(s => s[0] === width && s[1] === height)) {
                stored.push([width, height]);
            }
            
            // Keep max 20
            if (stored.length > 20) {
                stored.splice(0, stored.length - 20);
            }
            
            localStorage.setItem(key, JSON.stringify(stored));
            
            // Send to server after collecting 3+ or on page unload
            if (endpoint && stored.length >= 3) {
                this.sendToServer(id, src, endpoint, stored);
            } else if (endpoint) {
                // Save for next page
                window.addEventListener('beforeunload', () => {
                    this.sendToServer(id, src, endpoint, stored);
                });
            }
        },
        
        sendToServer: function(id, src, endpoint, resolutions) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content 
                || document.querySelector('input[name="_token"]')?.value;
            
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id: id,
                    src: src,
                    res: resolutions,
                    resolutions: resolutions  // Support both formats
                })
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    localStorage.removeItem('perfect_image_res_' + id);
                    // Reload to get srcset
                    setTimeout(() => location.reload(), 100);
                }
            }).catch(() => {});
        },
        
        observeResize: function() {
            window.addEventListener('resize', () => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    const images = document.querySelectorAll('[data-perfect-image-id]');
                    images.forEach(img => this.trackImage(img));
                }, 500);
            });
        }
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => PerfectImage.init());
    } else {
        PerfectImage.init();
    }
    
    window.PerfectImage = PerfectImage;
})();
</script>
