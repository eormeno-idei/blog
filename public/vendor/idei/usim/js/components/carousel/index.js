/**
 * Carousel Component
 *
 * Supports manual and auto modes for image/audio/video media.
 */
class CarouselComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.timer = null;
        this.mediaElement = null;
        this.slideElement = null;
        this.indicatorsElement = null;
    }

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };

        if (!this.element) {
            return;
        }

        this.applyFullscreenState();
        this.updateMedia();
        this.updateControls();
        this.updateIndicators();
        this.scheduleAutoplay();
    }

    render() {
        const wrapper = document.createElement('div');
        wrapper.className = 'usim-carousel';
        this.applyCommonAttributes(wrapper);
        this.element = wrapper;

        if (this.config.fullscreen) {
            wrapper.classList.add('is-fullscreen');
        }

        const frame = document.createElement('div');
        frame.className = 'usim-carousel-frame';

        const slide = document.createElement('div');
        slide.className = 'usim-carousel-slide';
        this.slideElement = slide;

        frame.appendChild(slide);
        wrapper.appendChild(frame);

        this.indicatorsElement = document.createElement('div');
        this.indicatorsElement.className = 'usim-carousel-indicators';
        wrapper.appendChild(this.indicatorsElement);

        this.prevButton = this.createControlButton('Prev', 'prev');
        this.nextButton = this.createControlButton('Next', 'next');

        frame.appendChild(this.prevButton);
        frame.appendChild(this.nextButton);

        this.updateMedia();
        this.updateControls();
        this.updateIndicators();
        this.scheduleAutoplay();

        return wrapper;
    }

    createControlButton(label, direction) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `usim-carousel-control ${direction}`;
        button.textContent = direction === 'prev' ? '‹' : '›';
        button.setAttribute('aria-label', label);

        button.addEventListener('click', () => {
            const action = direction === 'prev' ? this.config.prev_action : this.config.next_action;
            if (!action) {
                return;
            }

            this.sendEventToBackend('click', action, {
                carousel_name: this.config.name || null,
                current_index: this.config.current_index || 0,
                direction,
            });
        });

        return button;
    }

    updateMedia() {
        if (!this.slideElement) {
            return;
        }

        this.slideElement.innerHTML = '';

        const media = this.resolveCurrentMedia();
        if (!media) {
            const emptyState = document.createElement('div');
            emptyState.className = 'usim-carousel-empty';
            emptyState.textContent = 'No media available';
            this.slideElement.appendChild(emptyState);
            return;
        }

        const kind = (media.kind || '').toLowerCase();

        if (kind === 'image') {
            const img = document.createElement('img');
            img.className = 'usim-carousel-media image';
            img.src = media.url || '';
            img.alt = media.title || 'carousel image';
            this.slideElement.appendChild(img);
            this.mediaElement = img;
            return;
        }

        if (kind === 'video') {
            const video = document.createElement('video');
            video.className = 'usim-carousel-media video';
            video.src = media.url || '';
            video.controls = true;
            video.autoplay = true;
            video.loop = Boolean(this.config.loop);
            video.playsInline = true;
            this.slideElement.appendChild(video);
            this.mediaElement = video;
            return;
        }

        if (kind === 'audio') {
            const audioWrap = document.createElement('div');
            audioWrap.className = 'usim-carousel-audio-wrap';

            const audio = document.createElement('audio');
            audio.className = 'usim-carousel-media audio';
            audio.src = media.url || '';
            audio.controls = true;
            audio.autoplay = true;
            audio.loop = Boolean(this.config.loop);

            const title = document.createElement('div');
            title.className = 'usim-carousel-audio-title';
            title.textContent = media.title || 'Audio track';

            audioWrap.appendChild(title);
            audioWrap.appendChild(audio);
            this.slideElement.appendChild(audioWrap);
            this.mediaElement = audio;
            return;
        }

        const unsupported = document.createElement('div');
        unsupported.className = 'usim-carousel-empty';
        unsupported.textContent = 'Unsupported media type';
        this.slideElement.appendChild(unsupported);
    }

    resolveCurrentMedia() {
        if (this.config.current_media && this.config.current_media.url) {
            return this.config.current_media;
        }

        const items = Array.isArray(this.config.items) ? this.config.items : [];
        if (!items.length) {
            return null;
        }

        const index = this.normalizeIndex(this.config.current_index || 0, items.length);
        return items[index] || null;
    }

    normalizeIndex(index, length) {
        if (length <= 0) {
            return 0;
        }

        if (this.config.loop) {
            return ((index % length) + length) % length;
        }

        return Math.max(0, Math.min(index, length - 1));
    }

    updateControls() {
        const showManualControls = this.config.mode === 'manual';
        this.prevButton.style.display = showManualControls && this.config.show_prev !== false ? 'flex' : 'none';
        this.nextButton.style.display = showManualControls && this.config.show_next !== false ? 'flex' : 'none';
    }

    updateIndicators() {
        if (!this.indicatorsElement) {
            return;
        }

        const count = Number.isInteger(this.config.known_count) ? this.config.known_count : null;
        const position = this.config.indicator_position || 'bottom';
        this.indicatorsElement.className = `usim-carousel-indicators ${position}`;

        if (!count || count <= 0 || position === 'none') {
            this.indicatorsElement.innerHTML = '';
            this.indicatorsElement.style.display = 'none';
            return;
        }

        this.indicatorsElement.style.display = 'flex';
        this.indicatorsElement.innerHTML = '';

        const active = this.normalizeIndex(this.config.current_index || 0, count);
        for (let i = 0; i < count; i += 1) {
            const dot = document.createElement('span');
            dot.className = `usim-carousel-dot ${i === active ? 'active' : ''}`;
            this.indicatorsElement.appendChild(dot);
        }
    }

    applyFullscreenState() {
        if (!this.element) {
            return;
        }

        this.element.classList.toggle('is-fullscreen', Boolean(this.config.fullscreen));
    }

    scheduleAutoplay() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }

        const autoplay = this.config.autoplay || {};
        const isEnabled = this.config.mode === 'auto' && autoplay.enabled !== false;
        if (!isEnabled) {
            return;
        }

        const action = autoplay.action;
        const timeoutMs = Math.max(1, Number(autoplay.timeout_ms || 5000));
        if (!action) {
            return;
        }

        this.timer = setTimeout(() => {
            const media = this.resolveCurrentMedia();
            this.sendEventToBackend('timeout', action, {
                carousel_name: this.config.name || null,
                current_index: this.config.current_index || 0,
                current_media: media,
            });
        }, timeoutMs);
    }
}

window.CarouselComponent = CarouselComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.register('carousel', (id, config) => new CarouselComponent(id, config), {
        source: 'external',
    });
}
