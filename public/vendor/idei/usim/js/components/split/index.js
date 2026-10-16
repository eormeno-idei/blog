class UsimSplitComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.firstPaneEl = null;
        this.secondPaneEl = null;
        this.dividerEl = null;
        this.collapseButtonEl = null;
        this.titleEl = null;
        this.dragState = null;
        this.lastExpandedSize = null;
        this.observer = null;
        this.resizeObserver = null;
        this._lastObservedSize = null;
    }

    render() {
        const root = document.createElement('div');
        root.className = 'ui-container ui-container-card ui-split ui-split--horizontal';

        this.titleEl = document.createElement('div');
        this.titleEl.className = 'title';
        this.titleEl.style.display = 'none';
        root.appendChild(this.titleEl);

        this.firstPaneEl = document.createElement('div');
        this.firstPaneEl.className = 'ui-split-pane ui-split-pane--first';

        this.dividerEl = document.createElement('div');
        this.dividerEl.className = 'ui-split-divider';

        const handleEl = document.createElement('span');
        handleEl.className = 'ui-split-divider-handle';
        this.dividerEl.appendChild(handleEl);

        this.collapseButtonEl = document.createElement('button');
        this.collapseButtonEl.type = 'button';
        this.collapseButtonEl.className = 'ui-split-collapse-button';
        this.collapseButtonEl.setAttribute('aria-label', 'Toggle split collapse');
        this.dividerEl.appendChild(this.collapseButtonEl);

        this.secondPaneEl = document.createElement('div');
        this.secondPaneEl.className = 'ui-split-pane ui-split-pane--second';

        root.appendChild(this.firstPaneEl);
        root.appendChild(this.dividerEl);
        root.appendChild(this.secondPaneEl);

        this._bindSplitBehavior();
        this._observeAndRouteChildContainers(root);
        this._observeResize(root);

        this.element = this.applyCommonAttributes(root);
        this._applyConfig();

        return this.element;
    }

    update(newConfig) {
        const oldSplitSize = this.config?.split_size;
        this.config = {
            ...this.config,
            ...newConfig,
        };

        if (!this.element) {
            return;
        }

        if (newConfig?.split_size !== undefined && newConfig.split_size !== oldSplitSize) {
            this.lastExpandedSize = null;
        }

        this.applyCommonAttributes(this.element);
        this._applyConfig();
    }

    _applyConfig() {
        if (!this.element || !this.firstPaneEl || !this.secondPaneEl || !this.dividerEl || !this.collapseButtonEl) {
            return;
        }

        const orientation = this._normalizeOrientation(this.config.split_orientation);
        const appearance = this._normalizeAppearance(this.config.appearance);

        this.element.classList.remove('ui-container-card', 'ui-container-plain', 'ui-split--horizontal', 'ui-split--vertical');
        this.element.classList.add(`ui-container-${appearance}`);
        this.element.classList.add(`ui-split--${orientation}`);

        if (this.config.border_radius !== undefined && this.config.border_radius !== null && this.config.border_radius !== '') {
            const radiusValue = typeof this.config.border_radius === 'number' ? `${this.config.border_radius}px` : this.config.border_radius;
            this.element.style.setProperty('--split-radius', radiusValue);
        } else if (appearance === 'plain') {
            this.element.style.setProperty('--split-radius', '0px');
        } else {
            this.element.style.setProperty('--split-radius', '12px');
        }

        if (this.config.title) {
            this.titleEl.textContent = this.config.title;
            this.titleEl.style.display = '';
        } else {
            this.titleEl.textContent = '';
            this.titleEl.style.display = 'none';
        }

        const splitterSize = this._normalizeCssSize(this.config.splitter_size || '8px');
        this.dividerEl.style.setProperty('--splitter-size', splitterSize);

        const isCollapsible = this.config.collapsible === true;
        this.collapseButtonEl.style.display = isCollapsible ? '' : 'none';
        this.collapseButtonEl.disabled = !isCollapsible;

        if (orientation === 'horizontal') {
            this.dividerEl.style.width = splitterSize;
            this.dividerEl.style.height = '100%';
        } else {
            this.dividerEl.style.width = '100%';
            this.dividerEl.style.height = splitterSize;
        }

        this._applySplitSizeFromConfig();
        this._updateCollapseButtonIcon();
        this._syncDraggableState();
    }

    _bindSplitBehavior() {
        this.dividerEl.addEventListener('pointerdown', (event) => {
            if (this.config.draggable === false) {
                return;
            }

            if (event.target === this.collapseButtonEl) {
                return;
            }

            event.preventDefault();
            this._startDrag(event);
        });

        this.collapseButtonEl.addEventListener('click', (event) => {
            event.preventDefault();
            this._toggleCollapsed();
        });
    }

    _observeAndRouteChildContainers(root) {
        this.observer = new MutationObserver(() => {
            this._routeUnmanagedChildren();
        });

        this.observer.observe(root, { childList: true });
    }

    _observeResize(root) {
        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        this.resizeObserver = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const { width, height } = entry.contentRect;
                if (width <= 0 && height <= 0) {
                    continue;
                }

                if (this.dragState) {
                    continue;
                }

                const orientation = this._normalizeOrientation(this.config?.split_orientation);
                const currentSize = orientation === 'horizontal' ? width : height;

                if (this._lastObservedSize !== null && Math.abs(currentSize - this._lastObservedSize) < 1) {
                    continue;
                }

                this._lastObservedSize = currentSize;
                this._applySplitSizeFromConfig();
            }
        });

        this.resizeObserver.observe(root);
    }

    _routeUnmanagedChildren() {
        if (!this.element || !this.firstPaneEl || !this.secondPaneEl || !this.dividerEl || !this.titleEl) {
            return;
        }

        const children = Array.from(this.element.children);
        for (const child of children) {
            if (child === this.titleEl || child === this.firstPaneEl || child === this.dividerEl || child === this.secondPaneEl) {
                continue;
            }

            const targetPane = this.firstPaneEl.children.length === 0 ? this.firstPaneEl : this.secondPaneEl;
            targetPane.appendChild(child);
        }
    }

    _startDrag(event) {
        const orientation = this._normalizeOrientation(this.config.split_orientation);
        const rect = this.element.getBoundingClientRect();

        this.dragState = {
            orientation,
            pointerId: event.pointerId,
            startFirstPx: this._getFirstPaneSizePx(),
            startClient: orientation === 'horizontal' ? event.clientX : event.clientY,
            totalPx: orientation === 'horizontal' ? rect.width : rect.height,
        };

        this.dividerEl.setPointerCapture(event.pointerId);
        this.dividerEl.classList.add('is-dragging');

        const onMove = (moveEvent) => {
            if (!this.dragState || moveEvent.pointerId !== this.dragState.pointerId) {
                return;
            }

            const currentClient = this.dragState.orientation === 'horizontal' ? moveEvent.clientX : moveEvent.clientY;
            const delta = currentClient - this.dragState.startClient;
            const nextFirstPx = this.dragState.startFirstPx + delta;
            this._setSplitFromFirstPx(nextFirstPx);
        };

        const onEnd = (endEvent) => {
            if (!this.dragState || endEvent.pointerId !== this.dragState.pointerId) {
                return;
            }

            this.dividerEl.releasePointerCapture(endEvent.pointerId);
            this.dividerEl.classList.remove('is-dragging');
            this.lastExpandedSize = `${this._getFirstPaneSizePx()}px`;
            this.dragState = null;

            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onEnd);
            window.removeEventListener('pointercancel', onEnd);
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onEnd);
        window.addEventListener('pointercancel', onEnd);
    }

    _toggleCollapsed() {
        const current = this.config.collapsed_panel ?? null;
        if (current === 'first' || current === 'second') {
            this.config.collapsed_panel = null;
            this._applySplitSizeFromConfig();
            this._updateCollapseButtonIcon();
            return;
        }

        this.lastExpandedSize = `${this._getFirstPaneSizePx()}px`;
        this.config.collapsed_panel = this._normalizeCollapseTarget(this.config.collapse_target);
        this._applySplitSizeFromConfig();
        this._updateCollapseButtonIcon();
    }

    _applySplitSizeFromConfig() {
        const collapsedPanel = this.config.collapsed_panel;
        const collapseSize = this._normalizeCssSize(this.config.collapse_size || '0px');

        if (collapsedPanel === 'first') {
            this._applyCollapsedLayout('first', collapseSize);
            return;
        }

        if (collapsedPanel === 'second') {
            this._applyCollapsedLayout('second', collapseSize);
            return;
        }

        this.firstPaneEl.classList.remove('is-collapsed');
        this.secondPaneEl.classList.remove('is-collapsed');
        this.secondPaneEl.style.display = '';
        this.firstPaneEl.style.display = '';

        const preferredSize = this.lastExpandedSize || this.config.split_size || '50%';
        this._applySize(preferredSize);
    }

    _applyCollapsedLayout(panel, collapseSize) {
        const collapseTarget = panel === 'second' ? this.secondPaneEl : this.firstPaneEl;
        const expandedTarget = panel === 'second' ? this.firstPaneEl : this.secondPaneEl;

        collapseTarget.classList.add('is-collapsed');
        expandedTarget.classList.remove('is-collapsed');

        collapseTarget.style.flex = `0 0 ${collapseSize}`;
        collapseTarget.style.minWidth = collapseSize;
        collapseTarget.style.minHeight = collapseSize;

        expandedTarget.style.flex = '1 1 auto';
        expandedTarget.style.minWidth = '0';
        expandedTarget.style.minHeight = '0';
    }

    _applySize(rawSize) {
        const orientation = this._normalizeOrientation(this.config.split_orientation);
        const containerRect = this.element.getBoundingClientRect();
        const dividerSize = this._sizeToPx(this.config.splitter_size || '8px', orientation === 'horizontal' ? containerRect.width : containerRect.height);
        const totalPx = (orientation === 'horizontal' ? containerRect.width : containerRect.height) - dividerSize;

        if (totalPx <= 0) {
            const raw = String(rawSize || '50%').trim();
            const minFirst = this._normalizeCssSize(this.config.min_first_size || '120px');
            const minSecond = this._normalizeCssSize(this.config.min_second_size || '120px');

            this.firstPaneEl.style.flex = `0 0 ${raw}`;
            this.secondPaneEl.style.flex = '1 1 0%';
            if (orientation === 'horizontal') {
                this.firstPaneEl.style.minWidth = minFirst;
                this.secondPaneEl.style.minWidth = minSecond;
                this.firstPaneEl.style.minHeight = '0';
                this.secondPaneEl.style.minHeight = '0';
            } else {
                this.firstPaneEl.style.minHeight = minFirst;
                this.secondPaneEl.style.minHeight = minSecond;
                this.firstPaneEl.style.minWidth = '0';
                this.secondPaneEl.style.minWidth = '0';
            }
            return;
        }

        const firstPx = this._sizeToPx(rawSize, totalPx);
        this._setSplitFromFirstPx(firstPx);
    }

    _setSplitFromFirstPx(desiredFirstPx) {
        const orientation = this._normalizeOrientation(this.config.split_orientation);
        const rect = this.element.getBoundingClientRect();
        const rawTotal = orientation === 'horizontal' ? rect.width : rect.height;
        const dividerPx = this._sizeToPx(this.config.splitter_size || '8px', rawTotal);
        const totalPx = Math.max(rawTotal - dividerPx, 0);

        if (totalPx <= 0) {
            return;
        }

        const minFirstPx = this._sizeToPx(this.config.min_first_size || '120px', totalPx);
        const minSecondPx = this._sizeToPx(this.config.min_second_size || '120px', totalPx);
        const maxFirst = Math.max(totalPx - minSecondPx, minFirstPx);
        const boundedFirst = Math.max(minFirstPx, Math.min(desiredFirstPx, maxFirst));

        this.firstPaneEl.style.flex = `0 0 ${boundedFirst}px`;
        this.secondPaneEl.style.flex = '1 1 0%';
        this.firstPaneEl.style.minWidth = orientation === 'horizontal' ? `${minFirstPx}px` : '0';
        this.secondPaneEl.style.minWidth = orientation === 'horizontal' ? `${minSecondPx}px` : '0';
        this.firstPaneEl.style.minHeight = orientation === 'vertical' ? `${minFirstPx}px` : '0';
        this.secondPaneEl.style.minHeight = orientation === 'vertical' ? `${minSecondPx}px` : '0';
    }

    _getFirstPaneSizePx() {
        if (!this.firstPaneEl) {
            return 0;
        }

        const orientation = this._normalizeOrientation(this.config.split_orientation);
        const rect = this.firstPaneEl.getBoundingClientRect();
        return orientation === 'horizontal' ? rect.width : rect.height;
    }

    _syncDraggableState() {
        const draggable = this.config.draggable !== false;
        this.dividerEl.classList.toggle('is-draggable', draggable);
        this.dividerEl.classList.toggle('is-static', !draggable);
    }

    _updateCollapseButtonIcon() {
        const orientation = this._normalizeOrientation(this.config.split_orientation);
        const collapsedPanel = this.config.collapsed_panel;
        const collapseTarget = this._normalizeCollapseTarget(this.config.collapse_target);

        if (collapsedPanel === null) {
            if (orientation === 'horizontal') {
                this.collapseButtonEl.textContent = collapseTarget === 'first' ? '◀' : '▶';
            } else {
                this.collapseButtonEl.textContent = collapseTarget === 'first' ? '▲' : '▼';
            }
            return;
        }

        if (orientation === 'horizontal') {
            this.collapseButtonEl.textContent = collapsedPanel === 'first' ? '▶' : '◀';
        } else {
            this.collapseButtonEl.textContent = collapsedPanel === 'first' ? '▼' : '▲';
        }
    }

    _normalizeOrientation(value) {
        return String(value || 'horizontal').toLowerCase() === 'vertical' ? 'vertical' : 'horizontal';
    }

    _normalizeAppearance(value) {
        return String(value || 'card').toLowerCase() === 'plain' ? 'plain' : 'card';
    }

    _normalizeCollapseTarget(value) {
        return String(value || 'first').toLowerCase() === 'second' ? 'second' : 'first';
    }

    _normalizeCssSize(value) {
        if (typeof value === 'number') {
            return `${value}px`;
        }

        const trimmed = String(value || '').trim();
        if (!trimmed) {
            return '0px';
        }

        return /^\d+(\.\d+)?$/.test(trimmed) ? `${trimmed}px` : trimmed;
    }

    _sizeToPx(value, totalPx) {
        if (typeof value === 'number') {
            return value;
        }

        const raw = String(value || '').trim();
        if (!raw) {
            return 0;
        }

        if (raw.endsWith('%')) {
            const percent = Number.parseFloat(raw.slice(0, -1));
            if (!Number.isFinite(percent)) {
                return 0;
            }
            return Math.max((totalPx * percent) / 100, 0);
        }

        if (raw.endsWith('px')) {
            const pixels = Number.parseFloat(raw.slice(0, -2));
            return Number.isFinite(pixels) ? pixels : 0;
        }

        const numeric = Number.parseFloat(raw);
        return Number.isFinite(numeric) ? numeric : 0;
    }
}

window.UsimSplitComponent = UsimSplitComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('split');
    window.USIM_COMPONENTS.register('split', (id, config) => new UsimSplitComponent(id, config), {
        source: 'modular',
    });
}
