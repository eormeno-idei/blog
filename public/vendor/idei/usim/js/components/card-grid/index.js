/**
 * CardGrid component (modular implementation, `type: 'card_grid'`)
 *
 * Pure server-driven USIM responsive 2D container managing child `UsimItemCardComponent`
 * elements mounted directly via UIRenderer (`getChildMountTarget()`):
 * - Header (title, subtitle, count badge, optional global CTA)
 * - Integrated toolbar: debounced search, sort selector + direction toggle,
 *   declarative filters (select, multi_select, toggle, chips), active filter chips,
 *   and optional layout toggle
 * - Pagination footer: classic numbered 'pages' and incremental 'load_more'
 * - All queries (search, sort, filter, paginate, load_more) dispatch POST /api/ui-event
 *   so the backend owns the authoritative ItemCard tree and ComponentDiffer sends
 *   minimal ADD / UPDATE / DELETE diffs natively.
 */
class UsimCardGridComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);

        this._pagination = this._normalizePagination(config?.pagination);
        this._searchTerm = typeof config?.search_term === 'string' ? config.search_term : '';
        this._sortBy = typeof config?.sort_by === 'string' ? config.sort_by : '';
        this._sortDirection = config?.sort_direction === 'desc' ? 'desc' : 'asc';
        this._activeFilters = this._cloneFilters(config?.active_filters);
        this._cardLayout = config?.card_layout === 'horizontal' ? 'horizontal' : 'vertical';

        this._isQuerying = false;
        this._isAppending = false;
        this._debounceTimer = null;
        this._mobileFiltersOpen = false;
        this._openPopoverKey = null;
        this._orderSyncScheduled = false;

        this.wrapperElement = null;
        this.headerElement = null;
        this.toolbarElement = null;
        this.gridElement = null;
        this.stateHostElement = null;
        this.paginationElement = null;
        this.searchInputElement = null;
        this.liveRegionElement = null;
        this._gridObserver = null;

        this._boundDocumentClick = this._handleDocumentClick.bind(this);
        this._boundDocumentKeydown = this._handleDocumentKeydown.bind(this);
    }

    // ─── Normalization ────────────────────────────────────────────────────────

    _normalizeStyleToken(value) {
        const normalized = String(value || 'default').toLowerCase().trim();
        const allowed = ['default', 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'muted'];
        return allowed.includes(normalized) ? normalized : 'default';
    }

    _normalizePagination(raw) {
        const p = raw && typeof raw === 'object' ? raw : {};
        const perPage = Math.max(1, Number(p.per_page) || 12);
        const page = Math.max(1, Number(p.page) || 1);
        const total = p.total === null ? null : Math.max(0, Number(p.total) || 0);
        const totalPages = Number.isFinite(Number(p.total_pages))
            ? Math.max(0, Number(p.total_pages))
            : (total !== null && total > 0 ? Math.ceil(total / perPage) : 0);
        const hasMore = p.has_more !== undefined
            ? Boolean(p.has_more)
            : (total !== null ? (page * perPage) < total : false);

        const options = Array.isArray(p.per_page_options) && p.per_page_options.length > 0
            ? p.per_page_options.map((v) => Math.max(1, Number(v) || 12))
            : [12, 24, 36, 48];

        return {
            mode: p.mode === 'load_more' ? 'load_more' : 'pages',
            page,
            per_page: perPage,
            per_page_options: Array.from(new Set(options)),
            show_per_page: p.show_per_page !== false,
            total,
            total_pages: totalPages,
            has_more: hasMore,
            can_prev: p.can_prev !== undefined ? Boolean(p.can_prev) : page > 1,
            can_next: p.can_next !== undefined ? Boolean(p.can_next) : hasMore,
        };
    }

    _cloneFilters(raw) {
        if (!raw || typeof raw !== 'object') return {};
        const out = {};
        Object.entries(raw).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '' || value === false) return;
            if (Array.isArray(value)) {
                const clean = value.filter((v) => v !== null && v !== undefined && v !== '').map(String);
                if (clean.length > 0) out[key] = clean;
            } else {
                out[key] = value;
            }
        });
        return out;
    }

    // ─── USIM Child Mount Hook ────────────────────────────────────────────────

    /**
     * Tell UIRenderer to mount child `item_card` components directly inside `.usim-cg-grid`.
     */
    getChildMountTarget(childConfig, _childComponent) {
        if (childConfig && (childConfig.type === 'item_card' || childConfig.type === 'itemcard')) {
            return this.gridElement || this.element;
        }
        return this.gridElement || this.element;
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    render() {
        const wrapper = document.createElement('div');
        wrapper.className = this._getWrapperClasses();
        this.wrapperElement = wrapper;

        this.liveRegionElement = document.createElement('div');
        this.liveRegionElement.setAttribute('aria-live', 'polite');
        this.liveRegionElement.style.position = 'absolute';
        this.liveRegionElement.style.width = '1px';
        this.liveRegionElement.style.height = '1px';
        this.liveRegionElement.style.overflow = 'hidden';
        this.liveRegionElement.style.clip = 'rect(0 0 0 0)';

        this.headerElement = document.createElement('div');
        this.headerElement.className = 'usim-cg-header';

        this.toolbarElement = document.createElement('div');
        this.toolbarElement.className = 'usim-cg-toolbar';

        this.gridElement = document.createElement('div');
        this.gridElement.className = 'usim-cg-grid';
        this.gridElement.setAttribute('role', 'list');
        this._applyGridCssVars();

        this.stateHostElement = document.createElement('div');
        this.stateHostElement.className = 'usim-cg-state-host';

        this.paginationElement = document.createElement('div');
        this.paginationElement.className = 'usim-cg-pagination-host';

        wrapper.appendChild(this.liveRegionElement);
        wrapper.appendChild(this.headerElement);
        wrapper.appendChild(this.toolbarElement);
        wrapper.appendChild(this.gridElement);
        wrapper.appendChild(this.stateHostElement);
        wrapper.appendChild(this.paginationElement);

        this._renderHeader();
        this._renderToolbar();
        this._renderStateBox();
        this._renderPagination();

        document.addEventListener('click', this._boundDocumentClick);
        document.addEventListener('keydown', this._boundDocumentKeydown);

        if (typeof MutationObserver === 'function') {
            this._gridObserver = new MutationObserver(() => {
                this.scheduleOrderSync();
            });
            this._gridObserver.observe(this.gridElement, { childList: true });
        }

        return this.applyCommonAttributes(wrapper);
    }

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.applyCommonAttributes(this.wrapperElement);

        if (newConfig.card_layout !== undefined) {
            this._cardLayout = newConfig.card_layout === 'horizontal' ? 'horizontal' : 'vertical';
        }
        if (newConfig.search_term !== undefined) {
            const incomingSearch = typeof newConfig.search_term === 'string' ? newConfig.search_term : '';
            // Avoid overwriting user's active typing buffer if input is focused
            if (!this.searchInputElement || document.activeElement !== this.searchInputElement) {
                this._searchTerm = incomingSearch;
            }
        }
        if (newConfig.sort_by !== undefined) {
            this._sortBy = typeof newConfig.sort_by === 'string' ? newConfig.sort_by : '';
        }
        if (newConfig.sort_direction !== undefined) {
            this._sortDirection = newConfig.sort_direction === 'desc' ? 'desc' : 'asc';
        }
        if (newConfig.active_filters !== undefined) {
            this._activeFilters = this._cloneFilters(newConfig.active_filters);
        }
        if (newConfig.pagination !== undefined) {
            this._pagination = this._normalizePagination({ ...this._pagination, ...newConfig.pagination });
        }

        if (this.wrapperElement) {
            this.wrapperElement.className = this._getWrapperClasses();
        }
        this._applyGridCssVars();

        this._renderHeader();
        this._renderToolbar();
        this._renderStateBox();
        this._renderPagination();
        this.scheduleOrderSync();
    }

    destroy() {
        super.destroy();
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }
        if (this._gridObserver) {
            this._gridObserver.disconnect();
            this._gridObserver = null;
        }
        document.removeEventListener('click', this._boundDocumentClick);
        document.removeEventListener('keydown', this._boundDocumentKeydown);
    }

    _getWrapperClasses() {
        const classes = ['usim-cg-wrapper'];
        if (this.config.disabled) classes.push('is-disabled');
        return classes.join(' ');
    }

    _applyGridCssVars() {
        if (!this.gridElement) return;
        this.gridElement.style.setProperty('--usim-cg-min-width', String(this.config.min_card_width || '280px'));
        this.gridElement.style.setProperty('--usim-cg-gap', String(this.config.gap || '16px'));

        const maxCols = Number(this.config.max_columns);
        if (maxCols > 0) {
            this.gridElement.classList.add('has-max-cols');
            this.gridElement.style.setProperty('--usim-cg-max-cols', String(maxCols));
        } else {
            this.gridElement.classList.remove('has-max-cols');
            this.gridElement.style.removeProperty('--usim-cg-max-cols');
        }

        this.gridElement.classList.toggle('is-refreshing', this._isQuerying && !this._isAppending);
    }

    /**
     * Keep mounted ItemCard children strictly sorted by `data-position` in the DOM
     * so incremental sort/prepend/filter updates preserve both visual and Tab order.
     */
    scheduleOrderSync() {
        if (this._orderSyncScheduled) return;
        this._orderSyncScheduled = true;
        queueMicrotask(() => {
            this._orderSyncScheduled = false;
            this._syncCardOrderAndVisibility();
        });
    }

    _syncCardOrderAndVisibility() {
        if (!this.gridElement) return;

        const cards = Array.from(this.gridElement.children).filter(
            (el) => el instanceof HTMLElement && el.classList.contains('usim-ic-card')
        );

        if (cards.length > 1) {
            const sorted = [...cards].sort((a, b) => {
                const posA = Number(a.dataset.cardOrder ?? a.getAttribute('data-order') ?? 999999);
                const posB = Number(b.dataset.cardOrder ?? b.getAttribute('data-order') ?? 999999);
                return posA - posB;
            });

            let needsReorder = false;
            for (let i = 0; i < sorted.length; i++) {
                if (cards[i] !== sorted[i]) {
                    needsReorder = true;
                    break;
                }
            }

            if (needsReorder) {
                sorted.forEach((node) => this.gridElement.appendChild(node));
            }
        }

        this._renderStateBox();
        this._renderPagination();

        if (this.liveRegionElement) {
            const total = this._getEffectiveTotal();
            this.liveRegionElement.textContent = `${cards.length} tarjetas visibles de ${total} resultados`;
        }
    }

    // ─── Document Listeners (multi-select popovers) ───────────────────────────

    _handleDocumentClick(event) {
        if (!this.wrapperElement || this._openPopoverKey === null) return;
        const insidePopover = event.target?.closest?.('.usim-cg-multiselect');
        if (!insidePopover || insidePopover.dataset.filterName !== this._openPopoverKey) {
            this._openPopoverKey = null;
            this._renderToolbar();
        }
    }

    _handleDocumentKeydown(event) {
        if (event.key === 'Escape' && this._openPopoverKey !== null) {
            this._openPopoverKey = null;
            this._renderToolbar();
            event.stopPropagation();
        }
    }

    // ─── Header Rendering ─────────────────────────────────────────────────────

    _renderHeader() {
        if (!this.headerElement) return;
        this.headerElement.textContent = '';

        const hasTitle = Boolean(this.config.title);
        const hasSubtitle = Boolean(this.config.subtitle);
        const showCount = this.config.show_count !== false;
        const headerAction = this.config.header_action && typeof this.config.header_action === 'object'
            ? this.config.header_action
            : null;

        if (!hasTitle && !hasSubtitle && !headerAction) {
            this.headerElement.style.display = 'none';
            return;
        }

        this.headerElement.style.display = '';

        const left = document.createElement('div');
        left.className = 'usim-cg-header-text';

        if (hasTitle || showCount) {
            const titleRow = document.createElement('div');
            titleRow.className = 'usim-cg-title-row';

            if (hasTitle) {
                const h = document.createElement('h3');
                h.className = 'usim-cg-title';
                h.textContent = String(this.config.title);
                titleRow.appendChild(h);
            }

            if (showCount) {
                const count = document.createElement('span');
                count.className = 'usim-cg-count-badge';
                const total = this._getEffectiveTotal();
                count.textContent = total === 1 ? '1 resultado' : `${total} resultados`;
                titleRow.appendChild(count);
            }

            left.appendChild(titleRow);
        }

        if (hasSubtitle) {
            const sub = document.createElement('p');
            sub.className = 'usim-cg-subtitle';
            sub.textContent = String(this.config.subtitle);
            left.appendChild(sub);
        }

        this.headerElement.appendChild(left);

        if (headerAction && headerAction.label) {
            const right = document.createElement('div');
            right.className = 'usim-cg-header-actions';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `usim-ic-action-btn variant-solid style-${this._normalizeStyleToken(headerAction.style || 'primary')}`;
            if (headerAction.icon) {
                btn.appendChild(this._createIconNode(headerAction.icon, 15));
            }
            const labelSpan = document.createElement('span');
            labelSpan.textContent = String(headerAction.label);
            btn.appendChild(labelSpan);

            btn.addEventListener('click', () => {
                if (headerAction.action) {
                    this.sendEventToBackend('click', headerAction.action, headerAction.parameters || {});
                }
            });

            right.appendChild(btn);
            this.headerElement.appendChild(right);
        }
    }

    // ─── Integrated Toolbar Rendering ─────────────────────────────────────────

    _renderToolbar() {
        if (!this.toolbarElement) return;

        const searchable = this.config.searchable !== false;
        const filters = Array.isArray(this.config.filters) ? this.config.filters : [];
        const sortOptions = Array.isArray(this.config.sort_options) ? this.config.sort_options : [];
        const showLayoutToggle = Boolean(this.config.show_layout_toggle);

        if (!searchable && filters.length === 0 && sortOptions.length === 0 && !showLayoutToggle) {
            this.toolbarElement.style.display = 'none';
            return;
        }

        const hadSearchFocus = this.searchInputElement && document.activeElement === this.searchInputElement;
        const selectionStart = hadSearchFocus ? this.searchInputElement.selectionStart : null;
        const selectionEnd = hadSearchFocus ? this.searchInputElement.selectionEnd : null;

        this.toolbarElement.style.display = '';
        this.toolbarElement.textContent = '';

        const mainRow = document.createElement('div');
        mainRow.className = 'usim-cg-toolbar-main';

        // 1. Search Input
        if (searchable) {
            const searchBox = document.createElement('div');
            searchBox.className = 'usim-cg-search-box';

            const iconSpan = document.createElement('span');
            iconSpan.className = 'usim-cg-search-icon';
            iconSpan.appendChild(this._createIconNode('search', 15));
            searchBox.appendChild(iconSpan);

            const input = document.createElement('input');
            input.type = 'search';
            input.className = 'usim-cg-search-input';
            input.placeholder = String(this.config.search_placeholder || 'Buscar...');
            input.value = this._searchTerm;
            input.setAttribute('aria-label', input.placeholder);

            input.addEventListener('input', (e) => {
                this._searchTerm = e.target.value;
                this._onSearchChanged(false);
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._onSearchChanged(true);
                } else if (e.key === 'Escape' && this._searchTerm !== '') {
                    e.preventDefault();
                    this._searchTerm = '';
                    input.value = '';
                    this._onSearchChanged(true);
                }
            });

            this.searchInputElement = input;
            searchBox.appendChild(input);

            if (this._isQuerying && !this._isAppending) {
                const spinner = document.createElement('span');
                spinner.className = 'usim-cg-search-spinner';
                spinner.setAttribute('aria-hidden', 'true');
                searchBox.appendChild(spinner);
            } else if (this._searchTerm !== '') {
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.className = 'usim-cg-search-clear';
                clearBtn.textContent = '×';
                clearBtn.title = 'Limpiar búsqueda';
                clearBtn.setAttribute('aria-label', 'Limpiar búsqueda');
                clearBtn.addEventListener('click', () => {
                    this._searchTerm = '';
                    this._onSearchChanged(true);
                    this.searchInputElement?.focus();
                });
                searchBox.appendChild(clearBtn);
            }

            mainRow.appendChild(searchBox);
        } else {
            this.searchInputElement = null;
        }

        const inlineFilters = filters.filter((f) => f.type !== 'chips');
        const chipsFilters = filters.filter((f) => f.type === 'chips');
        const activeFilterCount = Object.keys(this._activeFilters).length;

        if (filters.length > 0) {
            const mobileBtn = document.createElement('button');
            mobileBtn.type = 'button';
            mobileBtn.className = `usim-cg-mobile-filters-btn${activeFilterCount > 0 ? ' has-active' : ''}`;
            mobileBtn.appendChild(this._createIconNode('filter', 14));
            const lbl = document.createElement('span');
            lbl.textContent = activeFilterCount > 0
                ? `${this.config.filters_label || 'Filtros'} (${activeFilterCount})`
                : String(this.config.filters_label || 'Filtros');
            mobileBtn.appendChild(lbl);
            mobileBtn.addEventListener('click', () => {
                this._mobileFiltersOpen = !this._mobileFiltersOpen;
                this._renderToolbar();
            });
            mainRow.appendChild(mobileBtn);
        }

        if (inlineFilters.length > 0) {
            const filtersBar = document.createElement('div');
            filtersBar.className = `usim-cg-filters-bar usim-cg-filters-collapsible${this._mobileFiltersOpen ? ' is-open' : ''}`;

            inlineFilters.forEach((filterDef) => {
                const ctrl = this._buildInlineFilterControl(filterDef);
                if (ctrl) filtersBar.appendChild(ctrl);
            });

            mainRow.appendChild(filtersBar);
        }

        if (sortOptions.length > 0 || showLayoutToggle) {
            const rightGroup = document.createElement('div');
            rightGroup.className = 'usim-cg-toolbar-right';

            if (sortOptions.length > 0) {
                const sortGroup = document.createElement('div');
                sortGroup.className = 'usim-cg-sort-group';

                const sortSelect = document.createElement('select');
                sortSelect.className = 'usim-cg-sort-select';
                sortSelect.setAttribute('aria-label', String(this.config.sort_label || 'Ordenar'));

                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = `${this.config.sort_label || 'Ordenar'}...`;
                sortSelect.appendChild(defaultOpt);

                sortOptions.forEach((opt) => {
                    const o = document.createElement('option');
                    o.value = String(opt.field);
                    o.textContent = String(opt.label);
                    if (this._sortBy === String(opt.field)) o.selected = true;
                    sortSelect.appendChild(o);
                });

                sortSelect.addEventListener('change', (e) => {
                    this._sortBy = e.target.value;
                    this._dispatchQuery({ page: 1, append: false });
                });

                sortGroup.appendChild(sortSelect);

                const dirBtn = document.createElement('button');
                dirBtn.type = 'button';
                dirBtn.className = 'usim-cg-icon-btn';
                dirBtn.textContent = this._sortDirection === 'desc' ? '↓' : '↑';
                dirBtn.title = this._sortDirection === 'desc' ? 'Orden descendente' : 'Orden ascendente';
                dirBtn.setAttribute('aria-label', dirBtn.title);
                dirBtn.addEventListener('click', () => {
                    this._sortDirection = this._sortDirection === 'desc' ? 'asc' : 'desc';
                    this._dispatchQuery({ page: 1, append: false });
                });

                sortGroup.appendChild(dirBtn);
                rightGroup.appendChild(sortGroup);
            }

            if (showLayoutToggle) {
                const toggleWrap = document.createElement('div');
                toggleWrap.className = 'usim-cg-layout-toggle';

                const vertBtn = document.createElement('button');
                vertBtn.type = 'button';
                vertBtn.className = `usim-cg-icon-btn${this._cardLayout === 'vertical' ? ' is-active' : ''}`;
                vertBtn.title = 'Vista vertical';
                vertBtn.setAttribute('aria-label', 'Vista vertical');
                vertBtn.appendChild(this._createIconNode('grid', 15));
                vertBtn.addEventListener('click', () => this._toggleLocalCardLayout('vertical'));

                const horizBtn = document.createElement('button');
                horizBtn.type = 'button';
                horizBtn.className = `usim-cg-icon-btn${this._cardLayout === 'horizontal' ? ' is-active' : ''}`;
                horizBtn.title = 'Vista horizontal';
                horizBtn.setAttribute('aria-label', 'Vista horizontal');
                horizBtn.appendChild(this._createIconNode('list', 15));
                horizBtn.addEventListener('click', () => this._toggleLocalCardLayout('horizontal'));

                toggleWrap.appendChild(vertBtn);
                toggleWrap.appendChild(horizBtn);
                rightGroup.appendChild(toggleWrap);
            }

            mainRow.appendChild(rightGroup);
        }

        this.toolbarElement.appendChild(mainRow);

        // Chips Filters
        if (chipsFilters.length > 0) {
            const chipsContainer = document.createElement('div');
            chipsContainer.className = `usim-cg-filters-collapsible${this._mobileFiltersOpen ? ' is-open' : ''}`;
            chipsContainer.style.display = this._mobileFiltersOpen ? 'flex' : '';
            chipsContainer.style.flexDirection = 'column';
            chipsContainer.style.gap = '8px';

            chipsFilters.forEach((filterDef) => {
                const row = document.createElement('div');
                row.className = 'usim-cg-chips-filter-row';

                const lbl = document.createElement('span');
                lbl.className = 'usim-cg-chips-filter-label';
                lbl.textContent = `${filterDef.label}:`;
                row.appendChild(lbl);

                const currentVal = this._activeFilters[filterDef.name] ?? '';
                (Array.isArray(filterDef.options) ? filterDef.options : []).forEach((opt) => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    const isActive = String(currentVal) === String(opt.value);
                    chip.className = `usim-cg-chip-btn${isActive ? ' is-active' : ''}`;
                    chip.textContent = String(opt.label);
                    chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    chip.addEventListener('click', () => {
                        if (isActive) {
                            delete this._activeFilters[filterDef.name];
                        } else {
                            this._activeFilters[filterDef.name] = String(opt.value);
                        }
                        this._dispatchQuery({ page: 1, append: false });
                    });
                    row.appendChild(chip);
                });

                chipsContainer.appendChild(row);
            });

            this.toolbarElement.appendChild(chipsContainer);
        }

        // Active Filter Chips Summary
        const activeChips = this._collectActiveFilterChips(filters);
        if (activeChips.length > 0) {
            const activeBar = document.createElement('div');
            activeBar.className = 'usim-cg-active-bar';

            activeChips.forEach((chipInfo) => {
                const pill = document.createElement('span');
                pill.className = 'usim-cg-active-chip';

                const text = document.createElement('span');
                text.textContent = `${chipInfo.label}: ${chipInfo.displayValue}`;
                pill.appendChild(text);

                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'usim-cg-active-chip-remove';
                rm.textContent = '×';
                rm.setAttribute('aria-label', `Quitar filtro ${chipInfo.label}`);
                rm.addEventListener('click', () => {
                    chipInfo.onRemove();
                    this._dispatchQuery({ page: 1, append: false });
                });

                pill.appendChild(rm);
                activeBar.appendChild(pill);
            });

            const clearAll = document.createElement('button');
            clearAll.type = 'button';
            clearAll.className = 'usim-cg-clear-all-btn';
            clearAll.textContent = String(this.config.clear_filters_label || 'Limpiar filtros');
            clearAll.addEventListener('click', () => this._clearAllCriteria());
            activeBar.appendChild(clearAll);

            this.toolbarElement.appendChild(activeBar);
        }

        if (hadSearchFocus && this.searchInputElement) {
            this.searchInputElement.focus();
            if (selectionStart !== null && selectionEnd !== null) {
                try {
                    this.searchInputElement.setSelectionRange(selectionStart, selectionEnd);
                } catch (_e) {
                    // Ignore
                }
            }
        }
    }

    _toggleLocalCardLayout(targetLayout) {
        if (this._cardLayout === targetLayout) return;
        this._cardLayout = targetLayout;
        if (this.gridElement) {
            this.gridElement.querySelectorAll('.usim-ic-card').forEach((cardEl) => {
                cardEl.classList.remove('layout-vertical', 'layout-horizontal');
                cardEl.classList.add(`layout-${targetLayout}`);
                const cid = cardEl.getAttribute('data-component-id');
                const childComp = cid ? window.globalRenderer?.components?.get?.(String(cid)) : null;
                if (childComp && childComp.config) {
                    childComp.config.layout = targetLayout;
                }
            });
        }
        this._renderToolbar();
    }

    _buildInlineFilterControl(filterDef) {
        const name = String(filterDef.name || '');
        const label = String(filterDef.label || name);
        const type = String(filterDef.type || 'select');

        if (type === 'select') {
            const select = document.createElement('select');
            const currentVal = this._activeFilters[name] ?? '';
            select.className = `usim-cg-filter-select${currentVal !== '' ? ' is-active' : ''}`;
            select.setAttribute('aria-label', label);

            const emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = String(filterDef.placeholder || `${label}: Todos`);
            select.appendChild(emptyOpt);

            (Array.isArray(filterDef.options) ? filterDef.options : []).forEach((opt) => {
                const o = document.createElement('option');
                o.value = String(opt.value);
                o.textContent = String(opt.label);
                if (String(currentVal) === String(opt.value)) o.selected = true;
                select.appendChild(o);
            });

            select.addEventListener('change', (e) => {
                const val = e.target.value;
                if (val === '') {
                    delete this._activeFilters[name];
                } else {
                    this._activeFilters[name] = val;
                }
                this._dispatchQuery({ page: 1, append: false });
            });

            return select;
        }

        if (type === 'multi_select') {
            const wrap = document.createElement('div');
            wrap.className = 'usim-cg-multiselect';
            wrap.dataset.filterName = name;

            const selectedValues = Array.isArray(this._activeFilters[name])
                ? this._activeFilters[name].map(String)
                : [];

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = `usim-cg-multiselect-trigger${selectedValues.length > 0 ? ' is-active' : ''}`;
            trigger.setAttribute('aria-expanded', this._openPopoverKey === name ? 'true' : 'false');

            const lblSpan = document.createElement('span');
            lblSpan.textContent = String(filterDef.placeholder || label);
            trigger.appendChild(lblSpan);

            if (selectedValues.length > 0) {
                const badge = document.createElement('span');
                badge.className = 'usim-cg-multiselect-count';
                badge.textContent = String(selectedValues.length);
                trigger.appendChild(badge);
            }

            const caret = document.createElement('span');
            caret.textContent = '▾';
            caret.style.fontSize = '0.75rem';
            trigger.appendChild(caret);

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                this._openPopoverKey = this._openPopoverKey === name ? null : name;
                this._renderToolbar();
            });

            wrap.appendChild(trigger);

            if (this._openPopoverKey === name) {
                const popover = document.createElement('div');
                popover.className = 'usim-cg-multiselect-popover';
                popover.addEventListener('click', (e) => e.stopPropagation());

                (Array.isArray(filterDef.options) ? filterDef.options : []).forEach((opt) => {
                    const row = document.createElement('label');
                    row.className = 'usim-cg-multiselect-option';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    const optVal = String(opt.value);
                    cb.checked = selectedValues.includes(optVal);
                    cb.addEventListener('change', () => {
                        const next = new Set(selectedValues);
                        if (cb.checked) next.add(optVal);
                        else next.delete(optVal);

                        if (next.size === 0) {
                            delete this._activeFilters[name];
                        } else {
                            this._activeFilters[name] = Array.from(next);
                        }
                        this._dispatchQuery({ page: 1, append: false });
                    });

                    const txt = document.createElement('span');
                    txt.textContent = String(opt.label);

                    row.appendChild(cb);
                    row.appendChild(txt);
                    popover.appendChild(row);
                });

                wrap.appendChild(popover);
            }

            return wrap;
        }

        if (type === 'toggle') {
            const isActive = Boolean(this._activeFilters[name]);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `usim-cg-toggle-filter${isActive ? ' is-active' : ''}`;
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            btn.textContent = label;
            btn.addEventListener('click', () => {
                if (isActive) delete this._activeFilters[name];
                else this._activeFilters[name] = true;
                this._dispatchQuery({ page: 1, append: false });
            });
            return btn;
        }

        return null;
    }

    _collectActiveFilterChips(filters) {
        const chips = [];
        const defMap = new Map();
        filters.forEach((f) => defMap.set(f.name, f));

        Object.entries(this._activeFilters).forEach(([key, value]) => {
            const def = defMap.get(key);
            const label = def ? def.label : key;

            if (Array.isArray(value)) {
                value.forEach((val) => {
                    const opt = def?.options?.find?.((o) => String(o.value) === String(val));
                    chips.push({
                        label,
                        displayValue: opt ? opt.label : String(val),
                        onRemove: () => {
                            const remaining = value.filter((v) => String(v) !== String(val));
                            if (remaining.length === 0) delete this._activeFilters[key];
                            else this._activeFilters[key] = remaining;
                        },
                    });
                });
            } else if (typeof value === 'boolean') {
                if (value) {
                    chips.push({
                        label,
                        displayValue: 'Sí',
                        onRemove: () => { delete this._activeFilters[key]; },
                    });
                }
            } else {
                const opt = def?.options?.find?.((o) => String(o.value) === String(value));
                chips.push({
                    label,
                    displayValue: opt ? opt.label : String(value),
                    onRemove: () => { delete this._activeFilters[key]; },
                });
            }
        });

        return chips;
    }

    // ─── Empty / No-Results / Min-Chars State ─────────────────────────────────

    _renderStateBox() {
        if (!this.stateHostElement || !this.gridElement) return;
        this.stateHostElement.textContent = '';

        const minChars = Number(this.config.min_search_chars || 0);
        const trimmedSearch = this._searchTerm.trim();

        if (minChars > 0 && trimmedSearch.length > 0 && trimmedSearch.length < minChars) {
            this.gridElement.classList.add('is-hidden');
            const msg = String(this.config.min_chars_message || 'Escribe al menos :count caracteres para buscar...')
                .replace(':count', String(minChars));
            this.stateHostElement.appendChild(this._buildStateBox('🔎', msg));
            return;
        }

        const visibleCount = this._getVisibleCardCount();
        if (visibleCount > 0) {
            this.gridElement.classList.remove('is-hidden');
            return;
        }

        this.gridElement.classList.add('is-hidden');
        const hasCriteria = trimmedSearch !== '' || Object.keys(this._activeFilters).length > 0;

        if (hasCriteria) {
            this.stateHostElement.appendChild(this._buildStateBox(
                '🔍',
                String(this.config.no_results_message || 'No se encontraron resultados con los criterios actuales'),
                {
                    label: String(this.config.clear_filters_label || 'Limpiar filtros'),
                    onClick: () => this._clearAllCriteria(),
                }
            ));
        } else {
            this.stateHostElement.appendChild(this._buildStateBox(
                '🗂️',
                String(this.config.empty_message || 'No hay elementos para mostrar')
            ));
        }
    }

    _buildStateBox(iconText, message, cta = null) {
        const box = document.createElement('div');
        box.className = 'usim-cg-state-box';

        const icon = document.createElement('div');
        icon.className = 'usim-cg-state-icon';
        icon.textContent = iconText;
        box.appendChild(icon);

        const p = document.createElement('p');
        p.className = 'usim-cg-state-message';
        p.textContent = message;
        box.appendChild(p);

        if (cta && cta.label && typeof cta.onClick === 'function') {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'usim-ic-action-btn variant-outline style-primary';
            btn.textContent = cta.label;
            btn.addEventListener('click', cta.onClick);
            box.appendChild(btn);
        }

        return box;
    }

    // ─── Pagination Rendering ─────────────────────────────────────────────────

    _renderPagination() {
        if (!this.paginationElement) return;
        this.paginationElement.textContent = '';

        const visibleCount = this._getVisibleCardCount();
        if (visibleCount === 0 && !this._isQuerying) {
            return;
        }

        const p = this._pagination;
        const total = this._getEffectiveTotal();

        if (p.mode === 'load_more') {
            const wrap = document.createElement('div');
            wrap.className = 'usim-cg-load-more-wrap';

            const info = document.createElement('div');
            info.className = 'usim-cg-pagination-info';
            info.textContent = p.total !== null
                ? `Mostrando ${visibleCount} de ${p.total}`
                : `Mostrando ${visibleCount} elementos`;
            wrap.appendChild(info);

            if (p.has_more) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'usim-cg-load-more-btn';
                btn.disabled = this._isQuerying;
                const remaining = p.total !== null ? Math.max(0, p.total - visibleCount) : null;
                const baseLabel = String(this.config.load_more_label || 'Cargar más');
                btn.textContent = this._isAppending
                    ? 'Cargando...'
                    : (remaining !== null && remaining > 0 ? `${baseLabel} (${remaining} restantes)` : baseLabel);

                btn.addEventListener('click', () => {
                    this._dispatchQuery({ page: p.page + 1, append: true });
                });
                wrap.appendChild(btn);
            }

            this.paginationElement.appendChild(wrap);
            return;
        }

        if (p.total_pages <= 1 && !p.show_per_page) {
            return;
        }

        const bar = document.createElement('div');
        bar.className = 'usim-cg-pagination';

        const start = total > 0 ? ((p.page - 1) * p.per_page) + 1 : 0;
        const end = total > 0 ? Math.min(start + visibleCount - 1, total) : visibleCount;

        const info = document.createElement('div');
        info.className = 'usim-cg-pagination-info';
        info.textContent = p.total !== null
            ? `Mostrando ${start}–${end} de ${p.total}`
            : `Página ${p.page}`;
        bar.appendChild(info);

        const controls = document.createElement('div');
        controls.className = 'usim-cg-pagination-controls';

        if (p.show_per_page && p.per_page_options.length > 1) {
            const perPageSel = document.createElement('select');
            perPageSel.className = 'usim-cg-per-page-select';
            perPageSel.setAttribute('aria-label', 'Elementos por página');

            p.per_page_options.forEach((opt) => {
                const o = document.createElement('option');
                o.value = String(opt);
                o.textContent = `${opt} / pág.`;
                if (opt === p.per_page) o.selected = true;
                perPageSel.appendChild(o);
            });

            perPageSel.addEventListener('change', (e) => {
                this._pagination.per_page = Math.max(1, Number(e.target.value) || 12);
                this._dispatchQuery({ page: 1, append: false });
            });
            controls.appendChild(perPageSel);
        }

        if (p.total_pages > 1 || p.can_prev || p.can_next) {
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'usim-cg-page-btn';
            prevBtn.textContent = '‹ Anterior';
            prevBtn.disabled = this._isQuerying || !p.can_prev;
            prevBtn.addEventListener('click', () => this._dispatchQuery({ page: p.page - 1, append: false, scrollToTop: true }));
            controls.appendChild(prevBtn);

            if (p.total_pages > 1) {
                this._computePageNumbers(p.page, p.total_pages).forEach((entry) => {
                    if (entry === '...') {
                        const dots = document.createElement('span');
                        dots.className = 'usim-cg-page-ellipsis';
                        dots.textContent = '…';
                        controls.appendChild(dots);
                        return;
                    }

                    const pageBtn = document.createElement('button');
                    pageBtn.type = 'button';
                    pageBtn.className = `usim-cg-page-btn${entry === p.page ? ' is-active' : ''}`;
                    pageBtn.textContent = String(entry);
                    pageBtn.disabled = this._isQuerying || entry === p.page;
                    if (entry === p.page) pageBtn.setAttribute('aria-current', 'page');
                    pageBtn.addEventListener('click', () => this._dispatchQuery({ page: entry, append: false, scrollToTop: true }));
                    controls.appendChild(pageBtn);
                });
            }

            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'usim-cg-page-btn';
            nextBtn.textContent = 'Siguiente ›';
            nextBtn.disabled = this._isQuerying || !p.can_next;
            nextBtn.addEventListener('click', () => this._dispatchQuery({ page: p.page + 1, append: false, scrollToTop: true }));
            controls.appendChild(nextBtn);
        }

        bar.appendChild(controls);
        this.paginationElement.appendChild(bar);
    }

    _computePageNumbers(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

        const pages = [1];
        const left = Math.max(2, current - 1);
        const right = Math.min(total - 1, current + 1);

        if (left > 2) pages.push('...');
        for (let i = left; i <= right; i++) pages.push(i);
        if (right < total - 1) pages.push('...');
        pages.push(total);

        return pages;
    }

    // ─── Event-Driven Querying (POST /api/ui-event) ───────────────────────────

    _onSearchChanged(immediate = false) {
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }

        const delay = immediate ? 0 : Number(this.config.debounce ?? 300);

        const run = () => {
            const minChars = Number(this.config.min_search_chars || 0);
            const term = this._searchTerm.trim();
            if (minChars > 0 && term.length > 0 && term.length < minChars) {
                this._renderToolbar();
                this._renderStateBox();
                this._renderPagination();
                return;
            }
            this._dispatchQuery({ page: 1, append: false });
        };

        if (delay > 0) {
            this._debounceTimer = setTimeout(run, delay);
        } else {
            run();
        }
    }

    _clearAllCriteria() {
        this._searchTerm = '';
        this._activeFilters = {};
        this._openPopoverKey = null;
        this._dispatchQuery({ page: 1, append: false });
    }

    async _dispatchQuery(options = {}) {
        const queryAction = this.config.query_action;
        if (!queryAction) return;

        const targetPage = Math.max(1, Number(options.page) || 1);
        const append = Boolean(options.append);

        this._isQuerying = true;
        this._isAppending = append;
        this._applyGridCssVars();
        this._renderToolbar();
        this._renderPagination();

        try {
            await this.sendEventToBackend('action', queryAction, {
                ...(this.config.parameters || {}),
                page: targetPage,
                per_page: this._pagination.per_page,
                search: this._searchTerm.trim(),
                sort_by: this._sortBy || null,
                sort_direction: this._sortDirection,
                filters: this._activeFilters,
                append,
            });

            if (options.scrollToTop) {
                this._scrollIntoViewIfNeeded();
            }
        } finally {
            this._isQuerying = false;
            this._isAppending = false;
            this._applyGridCssVars();
            this._renderToolbar();
            this._renderStateBox();
            this._renderPagination();
        }
    }

    _scrollIntoViewIfNeeded() {
        if (!this.wrapperElement) return;
        const rect = this.wrapperElement.getBoundingClientRect();
        if (rect.top < 0) {
            this.wrapperElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    _getVisibleCardCount() {
        if (this.gridElement) {
            const domCount = this.gridElement.querySelectorAll(':scope > .usim-ic-card').length;
            if (domCount > 0) return domCount;
        }
        return Math.max(0, Number(this.config.visible_count) || 0);
    }

    _getEffectiveTotal() {
        return this._pagination.total !== null ? this._pagination.total : this._getVisibleCardCount();
    }

    _createIconNode(iconValue, size = 16) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (helpers && typeof helpers.createIconElement === 'function') {
            return helpers.createIconElement(iconValue, {
                size,
                baseClass: 'usim-cg-icon',
            });
        }

        const fallback = document.createElement('span');
        fallback.className = 'usim-cg-icon';
        fallback.textContent = String(iconValue || '');
        return fallback;
    }
}

window.UsimCardGridComponent = UsimCardGridComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('card_grid');
    window.USIM_COMPONENTS.register('card_grid', (id, config) => new UsimCardGridComponent(id, config), {
        source: 'modular',
    });
}
