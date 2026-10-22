/**
 * Image component (modular override)
 *
 * Renders an image element wrapped in a container that supports:
 * - Dynamic data fetching via action (GET /api/ui-data)
 * - Loading spinner overlay while fetching
 * - Automatic blob URL creation and cleanup
 * - Fallback image handling on error
 * - Direct static URL (src) support
 * - Captions and responsive styling
 */
class UsimImageComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.imgElement = null;
        this.wrapper = null;
        this.blobUrl = null;
    }

    render() {
        const wrapper = document.createElement('div');
        wrapper.className = 'ui-image-wrapper';

        if (this.config.shadow) wrapper.classList.add('ui-image-shadow');
        if (this.config.border) wrapper.classList.add('ui-image-border');

        if (this.config.width) wrapper.style.width = this.config.width;
        if (this.config.height) wrapper.style.height = this.config.height;
        if (this.config.border_radius) {
            wrapper.style.borderRadius = this.config.border_radius;
        }

        const img = document.createElement('img');
        img.className = 'ui-image';
        img.alt = this.config.alt || '';

        if (this.config.lazy) {
            img.loading = 'lazy';
        }

        if (this.config.object_fit) {
            img.style.objectFit = this.config.object_fit;
        }

        if (this.config.border_radius) {
            img.style.borderRadius = this.config.border_radius;
        }

        this.imgElement = img;
        this.wrapper = wrapper;

        // Error handling fallback
        if (this.config.fallback_src) {
            img.addEventListener('error', () => {
                if (img.src !== this.config.fallback_src) {
                    img.src = this.config.fallback_src;
                }
            });
        }

        wrapper.appendChild(img);

        if (this.config.caption) {
            const caption = document.createElement('div');
            caption.className = 'ui-image-caption';
            caption.textContent = this.config.caption;
            wrapper.appendChild(caption);
        }

        // Attach attributes to the outer wrapper
        this.applyCommonAttributes(wrapper);

        // Show the initial source while an asynchronous image request is running.
        if (this.config.src) {
            img.src = this.config.src;
        }

        if (this.config.action && this.config.auto_fetch !== false) {
            // Queue fetch on mount
            setTimeout(() => {
                this.loadImage();
            }, 0);
        }

        return wrapper;
    }

    /**
     * Fetch image dynamically using the GET /api/ui-data feature.
     */
    async loadImage() {
        if (!this.config.action) return;

        try {
            // fetchData automatically sets loading=true, shows spinner and disables
            const result = await this.fetchData(this.config.action, this.config.parameters || {});

            if (!result) {
                if (this.config.fallback_src && this.imgElement) {
                    this.imgElement.src = this.config.fallback_src;
                }
                return;
            }

            // If result is a Blob
            if (result instanceof Blob) {
                if (this.blobUrl) {
                    URL.revokeObjectURL(this.blobUrl);
                }
                this.blobUrl = URL.createObjectURL(result);
                if (this.imgElement) {
                    this.imgElement.src = this.blobUrl;
                }
            } else if (typeof result === 'string') {
                // If result is a base64 string, SVG or URL
                if (result.startsWith('data:') || result.startsWith('http') || result.startsWith('/')) {
                    if (this.imgElement) {
                        this.imgElement.src = result;
                    }
                } else if (result.includes('<svg')) {
                    // Inline SVG blob
                    const svgBlob = new Blob([result], { type: 'image/svg+xml' });
                    if (this.blobUrl) URL.revokeObjectURL(this.blobUrl);
                    this.blobUrl = URL.createObjectURL(svgBlob);
                    if (this.imgElement) {
                        this.imgElement.src = this.blobUrl;
                    }
                } else {
                    // Raw string / URL
                    if (this.imgElement) {
                        this.imgElement.src = result;
                    }
                }
            } else if (result && typeof result === 'object' && result.url) {
                if (this.imgElement) {
                    this.imgElement.src = result.url;
                }
            }
        } catch (error) {
            console.error('Failed to load dynamic image:', error);
            if (this.config.fallback_src && this.imgElement) {
                this.imgElement.src = this.config.fallback_src;
            }
        }
    }

    destroy() {
        super.destroy();
        if (this.blobUrl) {
            URL.revokeObjectURL(this.blobUrl);
            this.blobUrl = null;
        }
    }
}

window.UsimImageComponent = UsimImageComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('image');
    window.USIM_COMPONENTS.register('image', (id, config) => new UsimImageComponent(id, config), {
        source: 'modular',
    });
}
