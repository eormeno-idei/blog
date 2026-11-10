/**
 * ItemList component (modular implementation)
 *
 * Vertical generic list component supporting:
 * - Selection modes: 'none', 'single', 'multiple' (with Select All & persisted cache across searches)
 * - CRUD operations:
 *    - Create: inline quick-add input or header button triggering backend/modal event
 *    - Read: rich vertical rows with icon, label, subtitle, and badge
 *    - Update: inline row text editing or button triggering backend/modal event
 *    - Delete: direct per-row delete event + bulk delete of selected items
 * - Search & Filtering:
 *    - Asynchronous server query via GET /api/ui-data (action + parameters + debounce + min_search_chars)
 *    - Instant client-side filtering when items are static
 * - Form integration:
 *    - Hidden inputs synchronized with selection so UsimButtonComponent.collectContextValues()
 *      collects selected IDs automatically in forms and modals.
 */
class UsimItemListComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this._items = [];
        this._selectedSet = new Set();
        this._selectedRawMap = new Map();
        this._selectedItemsCache = new Map();
        this._editingItemId = null;
        this._searchTerm = typeof config?.search_term === 'string' ? config.search_term : '';
        this._debounceTimer = null;
        this._hasFetchedOnce = false;

        this.wrapperElement = null;
        this.boxElement = null;
        this.headerElement = null;
        this.toolbarElement = null;
        this.searchInputElement = null;
        this.createInputElement = null;
        this.bodyWrapperElement = null;
        this.listElement = null;
        this.formInputsContainer = null;

        this._syncInternalStateFromConfig(this.config);
    }

    // ─── State Normalization ──────────────────────────────────────────────────

    _normalizeSelectionMode(value) {
        const normalized = String(value || 'none').toLowerCase().trim();
        if (normalized === 'single' || normalized === 'multiple') {
            return normalized;
        }
        return 'none';
    }

    _normalizeItems(rawItems) {
        if (!rawItems) return [];

        const normalized = [];

        if (Array.isArray(rawItems)) {
            rawItems.forEach((item, idx) => {
                normalized.push(this._normalizeSingleItem(item, idx));
            });
        } else if (typeof rawItems === 'object') {
            Object.entries(rawItems).forEach(([key, val], idx) => {
                if (val !== null && typeof val === 'object') {
                    normalized.push(this._normalizeSingleItem({ id: key, ...val }, idx));
                } else {
                    normalized.push({
                        id: key,
                        label: val !== null && val !== undefined ? String(val) : String(key),
                        subtitle: null,
                        icon: null,
                        badge: null,
                        badge_style: null,
                        disabled: false,
                        editable: true,
                        deletable: true,
                    });
                }
            });
        }

        return normalized;
    }

    _normalizeSingleItem(item, fallbackIdx = 0) {
        if (item !== null && typeof item === 'object') {
            const rawId = item.id ?? item.value ?? item._model_id ?? fallbackIdx;
            const id = (typeof rawId === 'number' || typeof rawId === 'string') ? rawId : String(fallbackIdx);
            const rawLabel = item.label ?? item.title ?? item.name ?? String(id);
            const rawSubtitle = item.subtitle ?? item.description ?? null;

            return {
                ...item,
                id,
                label: String(rawLabel),
                subtitle: rawSubtitle !== null && rawSubtitle !== undefined && rawSubtitle !== '' ? String(rawSubtitle) : null,
                icon: item.icon ? String(item.icon) : null,
                badge: item.badge !== null && item.badge !== undefined && item.badge !== '' ? String(item.badge) : null,
                badge_style: item.badge_style ? String(item.badge_style) : 'default',
                disabled: Boolean(item.disabled),
                editable: item.editable !== undefined ? Boolean(item.editable) : true,
                deletable: item.deletable !== undefined ? Boolean(item.deletable) : true,
            };
        }

        const scalar = item !== null && item !== undefined ? String(item) : String(fallbackIdx);
        return {
            id: scalar,
            label: scalar,
            subtitle: null,
            icon: null,
            badge: null,
            badge_style: 'default',
            disabled: false,
            editable: true,
            deletable: true,
        };
    }

    _syncInternalStateFromConfig(configSource = this.config) {
        this._items = this._normalizeItems(configSource?.items || []);

        const mode = this._normalizeSelectionMode(configSource?.selection_mode);
        const rawSelected = configSource?.selected_items ?? configSource?.value ?? [];
        let selectedList = [];

        if (Array.isArray(rawSelected)) {
            selectedList = rawSelected;
        } else if (rawSelected !== null && rawSelected !== undefined && rawSelected !== '') {
            selectedList = [rawSelected];
        }

        if (mode === 'none') {
            selectedList = [];
        } else if (mode === 'single' && selectedList.length > 1) {
            selectedList = [selectedList[0]];
        }

        this._selectedSet.clear();
        this._selectedRawMap.clear();

        selectedList.forEach((val) => {
            if (val === null || val === undefined || val === '') return;
            const key = String(val);
            this._selectedSet.add(key);
            this._selectedRawMap.set(key, val);
        });

        // Cache item metadata for selected items so they remain accessible across searches
        this._items.forEach((item) => {
            const key = String(item.id);
            if (this._selectedSet.has(key)) {
                this._selectedItemsCache.set(key, item);
                if (!this._selectedRawMap.has(key)) {
                    this._selectedRawMap.set(key, item.id);
                }
            } else if (this._selectedItemsCache.has(key)) {
                // Keep cache fresh if item was updated
                this._selectedItemsCache.set(key, item);
            }
        });

        // Prune deselected items from cache
        for (const cachedKey of Array.from(this._selectedItemsCache.keys())) {
            if (!this._selectedSet.has(cachedKey)) {
                this._selectedItemsCache.delete(cachedKey);
            }
        }
    }

    _getSelectedArray() {
        return Array.from(this._selectedSet).map((key) => (
            this._selectedRawMap.has(key) ? this._selectedRawMap.get(key) : key
        ));
    }

    _getSelectedValueForMode() {
        const mode = this._normalizeSelectionMode(this.config?.selection_mode);
        const arr = this._getSelectedArray();
        if (mode === 'none') return null;
        if (mode === 'single') return arr[0] ?? null;
        return arr;
    }

    // ─── Render Lifecycle ─────────────────────────────────────────────────────

    render() {
        const wrapper = document.createElement('div');
        wrapper.className = 'usim-item-list-wrapper';
        if (this.config.error) {
            wrapper.classList.add('has-error');
        }
        if (this.config.disabled) {
            wrapper.classList.add('is-disabled');
        }

        this.wrapperElement = wrapper;
        this.applyCommonAttributes(wrapper);

        // 1. Field Label + Help Tooltip (above box)
        this._renderLabelRow(wrapper);

        // 2. Main Box
        const box = document.createElement('div');
        const variant = this.config.variant || 'outlined';
        const density = this.config.density || 'comfortable';
        box.className = `usim-item-list-box variant-${variant} density-${density}`;
        if (this.config.show_dividers !== false) {
            box.classList.add('with-dividers');
        }
        this.boxElement = box;

        // 2a. Header (Title, Select All, Selection count, Bulk delete, Non-inline Create button)
        this.headerElement = document.createElement('div');
        this.headerElement.className = 'usim-item-list-header';
        box.appendChild(this.headerElement);

        // 2b. Toolbar (Search bar + Inline Create bar)
        this.toolbarElement = document.createElement('div');
        this.toolbarElement.className = 'usim-item-list-toolbar';
        box.appendChild(this.toolbarElement);

        // 2c. Body Wrapper (Scrollable list + loading spinner host)
        this.bodyWrapperElement = document.createElement('div');
        this.bodyWrapperElement.className = 'usim-item-list-body-wrapper';
        this._applyBodyDimensions();

        this.listElement = document.createElement('div');
        this.listElement.className = 'usim-item-list-body';
        this.listElement.setAttribute('role', 'listbox');
        if (this._normalizeSelectionMode(this.config.selection_mode) === 'multiple') {
            this.listElement.setAttribute('aria-multiselectable', 'true');
        }
        this.bodyWrapperElement.appendChild(this.listElement);
        box.appendChild(this.bodyWrapperElement);

        // 2d. Error Icon (consistent with USIM Input/Textarea)
        this._syncErrorState();

        wrapper.appendChild(box);

        // 3. Hidden Form Inputs for UsimButtonComponent.collectContextValues()
        this.formInputsContainer = document.createElement('div');
        this.formInputsContainer.className = 'usim-item-list-form-inputs';
        this.formInputsContainer.style.display = 'none';
        this.formInputsContainer.setAttribute('aria-hidden', 'true');
        wrapper.appendChild(this.formInputsContainer);

        // Populate sections
        this._renderHeader();
        this._renderToolbar();
        this._renderListBody();
        this._syncFormInputs();

        if (this.config.loading) {
            this.setLoading(true);
        }

        // Trigger initial GET /api/ui-data fetch if configured
        if (this.config.action && this.config.auto_fetch !== false) {
            setTimeout(() => {
                this.loadItems();
            }, 0);
        }

        this.element = wrapper;
        return wrapper;
    }

    update(changes) {
        if (!changes || typeof changes !== 'object') return;

        const prevAction = this.config.action;
        const prevParamsJson = JSON.stringify(this.config.parameters || {});
        const prevReloadToken = this.config.reload_token;

        this.config = {
            ...this.config,
            ...changes,
        };

        if (!this.element) return;

        this.applyCommonAttributes(this.element);

        if (changes.search_term !== undefined && changes.search_term !== null) {
            this._searchTerm = String(changes.search_term);
            if (this.searchInputElement && this.searchInputElement.value !== this._searchTerm) {
                this.searchInputElement.value = this._searchTerm;
            }
        }

        // Sync items or selection if updated from backend
        if (
            changes.items !== undefined ||
            changes.selected_items !== undefined ||
            changes.value !== undefined ||
            changes.selection_mode !== undefined
        ) {
            this._syncInternalStateFromConfig(this.config);
        }

        if (changes.label !== undefined || changes.help_text !== undefined || changes.required !== undefined) {
            this._renderLabelRow(this.wrapperElement, true);
        }

        if (changes.error !== undefined) {
            this._syncErrorState();
        }

        if (changes.max_height !== undefined || changes.min_height !== undefined) {
            this._applyBodyDimensions();
        }

        if (changes.variant !== undefined || changes.density !== undefined || changes.show_dividers !== undefined) {
            if (this.boxElement) {
                const variant = this.config.variant || 'outlined';
                const density = this.config.density || 'comfortable';
                this.boxElement.className = `usim-item-list-box variant-${variant} density-${density}`;
                if (this.config.show_dividers !== false) {
                    this.boxElement.classList.add('with-dividers');
                }
            }
        }

        // Rebuild toolbar only if structural toolbar configs changed
        if (
            changes.searchable !== undefined ||
            changes.search_placeholder !== undefined ||
            changes.creatable !== undefined ||
            changes.create_inline !== undefined ||
            changes.create_placeholder !== undefined ||
            changes.create_button_label !== undefined ||
            changes.disabled !== undefined ||
            changes.readonly !== undefined
        ) {
            this._renderToolbar();
        }

        this._renderHeader();
        this._renderListBody();
        this._syncFormInputs();

        if (changes.loading !== undefined) {
            this.setLoading(Boolean(changes.loading));
        }

        // Check if remote data should be re-fetched via GET /api/ui-data
        const nextParamsJson = JSON.stringify(this.config.parameters || {});
        const shouldReloadRemote = Boolean(this.config.action) && (
            (changes.reload_token !== undefined && changes.reload_token !== prevReloadToken) ||
            (changes.action !== undefined && changes.action !== prevAction && this.config.auto_fetch !== false) ||
            (changes.parameters !== undefined && nextParamsJson !== prevParamsJson && this.config.auto_fetch !== false)
        );

        if (shouldReloadRemote) {
            this.loadItems();
        }
    }

    // ─── Label & Error Helpers ────────────────────────────────────────────────

    _renderLabelRow(wrapper, replaceExisting = false) {
        if (!wrapper) return;
        const existing = wrapper.querySelector(':scope > .usim-item-list-label-row');
        if (existing) {
            if (!replaceExisting) return;
            existing.remove();
        }

        if (!this.config.label) return;

        const labelRow = document.createElement('div');
        labelRow.className = 'usim-item-list-label-row';

        const label = document.createElement('label');
        label.className = 'usim-item-list-label';
        if (this.config.required) {
            label.classList.add('required');
        }
        label.textContent = this.config.label;
        labelRow.appendChild(label);

        if (this.config.help_text) {
            const helpIcon = document.createElement('span');
            helpIcon.className = 'usim-item-list-help-icon';
            helpIcon.textContent = '?';
            helpIcon.setAttribute('role', 'img');
            helpIcon.setAttribute('aria-label', 'Ayuda');
            helpIcon.setAttribute('data-tooltip', this.config.help_text);
            labelRow.appendChild(helpIcon);
        }

        wrapper.insertBefore(labelRow, wrapper.firstChild);
    }

    _syncErrorState() {
        if (!this.wrapperElement || !this.boxElement) return;

        this.wrapperElement.classList.toggle('has-error', Boolean(this.config.error));
        let errorIcon = this.boxElement.querySelector(':scope > .usim-item-list-error-icon');

        if (this.config.error) {
            if (!errorIcon) {
                errorIcon = document.createElement('span');
                errorIcon.className = 'ui-input-error-icon usim-item-list-error-icon';
                errorIcon.innerHTML = '⚠️';
                this.boxElement.appendChild(errorIcon);
            }
            errorIcon.setAttribute('data-tooltip', String(this.config.error));
        } else if (errorIcon) {
            errorIcon.remove();
        }
    }

    _applyBodyDimensions() {
        if (!this.bodyWrapperElement) return;
        const normalizeSize = (val) => {
            if (val === null || val === undefined || val === '') return '';
            if (typeof val === 'number') return `${val}px`;
            const str = String(val).trim();
            return /^\d+$/.test(str) ? `${str}px` : str;
        };

        this.listElement && (this.listElement.style.maxHeight = normalizeSize(this.config.max_height) || '420px');
        this.listElement && (this.listElement.style.minHeight = normalizeSize(this.config.min_height) || '');
    }

    // ─── Header & Toolbar Rendering ───────────────────────────────────────────

    _renderHeader() {
        if (!this.headerElement) return;
        this.headerElement.innerHTML = '';

        const mode = this._normalizeSelectionMode(this.config.selection_mode);
        const visibleItems = this._getDisplayItems().filter((it) => !it.disabled);
        const showSelectAll = mode === 'multiple' && this.config.show_select_all !== false && visibleItems.length > 0;
        const hasTitle = Boolean(this.config.title);
        const isEditableCrud = !this.config.disabled && !this.config.readonly;
        const showModalCreateBtn = Boolean(this.config.creatable && this.config.create_inline === false && isEditableCrud);
        const showBulkDeleteBtn = Boolean(
            mode === 'multiple' &&
            (this.config.bulk_deletable || (this.config.deletable && this.config.bulk_deletable !== false)) &&
            this._selectedSet.size > 0 &&
            isEditableCrud
        );

        if (!hasTitle && !showSelectAll && !showModalCreateBtn && !showBulkDeleteBtn && mode !== 'multiple') {
            this.headerElement.style.display = 'none';
            return;
        }

        this.headerElement.style.display = 'flex';

        // Left side: Master Checkbox + Title + Count badge
        const left = document.createElement('div');
        left.className = 'usim-item-list-header-left';

        if (showSelectAll) {
            const masterCheckbox = document.createElement('input');
            masterCheckbox.type = 'checkbox';
            masterCheckbox.className = 'usim-item-list-select-all';
            masterCheckbox.disabled = Boolean(this.config.disabled);
            masterCheckbox.title = 'Seleccionar todos los visibles';

            const selectedVisibleCount = visibleItems.filter((it) => this._selectedSet.has(String(it.id))).length;
            masterCheckbox.checked = visibleItems.length > 0 && selectedVisibleCount === visibleItems.length;
            masterCheckbox.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleItems.length;

            masterCheckbox.addEventListener('change', () => {
                this._handleSelectAllVisible(masterCheckbox.checked, visibleItems);
            });

            left.appendChild(masterCheckbox);
        }

        if (hasTitle) {
            const titleEl = document.createElement('h4');
            titleEl.className = 'usim-item-list-title';
            titleEl.textContent = String(this.config.title);
            left.appendChild(titleEl);
        }

        const countBadge = document.createElement('span');
        countBadge.className = 'usim-item-list-count-badge';
        if (mode === 'multiple' && this._selectedSet.size > 0) {
            countBadge.classList.add('has-selection');
            countBadge.textContent = `${this._selectedSet.size} sel.`;
        } else {
            countBadge.textContent = `${this._items.length}`;
        }
        left.appendChild(countBadge);

        this.headerElement.appendChild(left);

        // Right side: Contextual actions (Bulk Delete, Non-inline Create)
        const right = document.createElement('div');
        right.className = 'usim-item-list-header-right';

        if (showBulkDeleteBtn) {
            const bulkDeleteBtn = document.createElement('button');
            bulkDeleteBtn.type = 'button';
            bulkDeleteBtn.className = 'usim-item-list-btn danger-soft usim-item-list-bulk-delete-btn';
            const label = this.config.bulk_delete_label || 'Eliminar';
            bulkDeleteBtn.innerHTML = `<span aria-hidden="true">🗑️</span> <span>${this._escapeHtml(label)} (${this._selectedSet.size})</span>`;
            bulkDeleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._handleBulkDelete();
            });
            right.appendChild(bulkDeleteBtn);
        }

        if (showModalCreateBtn) {
            const createBtn = document.createElement('button');
            createBtn.type = 'button';
            createBtn.className = 'usim-item-list-btn primary usim-item-list-create-modal-btn';
            const btnLabel = this.config.create_button_label || 'Agregar';
            createBtn.innerHTML = `<span aria-hidden="true">＋</span> <span>${this._escapeHtml(btnLabel)}</span>`;
            createBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._handleCreateModalTrigger();
            });
            right.appendChild(createBtn);
        }

        this.headerElement.appendChild(right);
    }

    _renderToolbar() {
        if (!this.toolbarElement) return;
        this.toolbarElement.innerHTML = '';

        const isEditableCrud = !this.config.disabled && !this.config.readonly;
        const showSearch = Boolean(this.config.searchable);
        const showInlineCreate = Boolean(this.config.creatable && this.config.create_inline !== false && isEditableCrud);

        if (!showSearch && !showInlineCreate) {
            this.toolbarElement.style.display = 'none';
            return;
        }

        this.toolbarElement.style.display = 'flex';

        // 1. Search Bar
        if (showSearch) {
            const searchBox = document.createElement('div');
            searchBox.className = 'usim-item-list-search-box';

            const searchIcon = document.createElement('span');
            searchIcon.className = 'usim-item-list-search-icon';
            searchIcon.setAttribute('aria-hidden', 'true');
            searchIcon.textContent = '🔍';
            searchBox.appendChild(searchIcon);

            const searchInput = document.createElement('input');
            searchInput.type = 'search';
            searchInput.className = 'usim-item-list-search-input';
            searchInput.placeholder = this.config.search_placeholder || 'Buscar...';
            searchInput.value = this._searchTerm;
            searchInput.disabled = Boolean(this.config.disabled);
            searchInput.autocomplete = 'off';
            // Intentionally NO `name` attribute so button.collectContextValues() does not treat search input as a form field
            this.searchInputElement = searchInput;

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'usim-item-list-search-clear';
            clearBtn.textContent = '✕';
            clearBtn.title = 'Limpiar búsqueda';
            clearBtn.style.display = this._searchTerm ? 'inline-flex' : 'none';

            searchInput.addEventListener('input', () => {
                this._searchTerm = searchInput.value;
                clearBtn.style.display = this._searchTerm ? 'inline-flex' : 'none';
                this._onSearchInputChanged();
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this._searchTerm) {
                    e.preventDefault();
                    e.stopPropagation();
                    searchInput.value = '';
                    this._searchTerm = '';
                    clearBtn.style.display = 'none';
                    this._onSearchInputChanged(true);
                }
            });

            clearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.value = '';
                this._searchTerm = '';
                clearBtn.style.display = 'none';
                searchInput.focus();
                this._onSearchInputChanged(true);
            });

            searchBox.appendChild(searchInput);
            searchBox.appendChild(clearBtn);
            this.toolbarElement.appendChild(searchBox);
        }

        // 2. Inline Create Bar
        if (showInlineCreate) {
            const createBox = document.createElement('div');
            createBox.className = 'usim-item-list-create-box';

            const createInput = document.createElement('input');
            createInput.type = 'text';
            createInput.className = 'usim-item-list-create-input';
            createInput.placeholder = this.config.create_placeholder || 'Nuevo elemento...';
            createInput.disabled = Boolean(this.config.disabled);
            createInput.autocomplete = 'off';
            this.createInputElement = createInput;

            const createBtn = document.createElement('button');
            createBtn.type = 'button';
            createBtn.className = 'usim-item-list-btn primary usim-item-list-create-btn';
            createBtn.disabled = Boolean(this.config.disabled);
            const btnLabel = this.config.create_button_label || 'Agregar';
            createBtn.innerHTML = `<span aria-hidden="true">＋</span> <span>${this._escapeHtml(btnLabel)}</span>`;

            createInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    this._handleCreateInline();
                } else if (e.key === 'Escape') {
                    createInput.value = '';
                }
            });

            createBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._handleCreateInline();
            });

            createBox.appendChild(createInput);
            createBox.appendChild(createBtn);
            this.toolbarElement.appendChild(createBox);
        }
    }

    // ─── List Body & Row Rendering ────────────────────────────────────────────

    _getDisplayItems() {
        const term = this._searchTerm.trim().toLowerCase();
        const minChars = Number(this.config.min_search_chars || 0);
        const hasRemoteAction = Boolean(this.config.action || this.config.on_search);

        // If client-side filtering (no remote action), filter by label/subtitle/badge
        let mainItems = this._items;
        if (!hasRemoteAction && term !== '') {
            if (term.length < minChars) {
                mainItems = [];
            } else {
                mainItems = this._items.filter((item) => {
                    const labelMatch = String(item.label || '').toLowerCase().includes(term);
                    const subMatch = String(item.subtitle || '').toLowerCase().includes(term);
                    const badgeMatch = String(item.badge || '').toLowerCase().includes(term);
                    return labelMatch || subMatch || badgeMatch;
                });
            }
        }

        // Preserve already-selected items visibility across searches if keep_selected_visible !== false
        if (this.config.keep_selected_visible !== false && this._selectedSet.size > 0) {
            const visibleIds = new Set(mainItems.map((it) => String(it.id)));
            const pinnedItems = [];

            for (const selKey of this._selectedSet) {
                if (!visibleIds.has(selKey) && this._selectedItemsCache.has(selKey)) {
                    pinnedItems.push({
                        ...this._selectedItemsCache.get(selKey),
                        _isPinnedSelection: true,
                    });
                }
            }

            if (pinnedItems.length > 0) {
                return [...pinnedItems, ...mainItems];
            }
        }

        return mainItems;
    }

    _renderListBody() {
        if (!this.listElement) return;
        this.listElement.innerHTML = '';

        const mode = this._normalizeSelectionMode(this.config.selection_mode);
        const term = this._searchTerm.trim();
        const minChars = Number(this.config.min_search_chars || 0);
        const displayItems = this._getDisplayItems();

        // Check if waiting for min_search_chars
        if (
            this.config.searchable &&
            minChars > 0 &&
            term.length > 0 &&
            term.length < minChars &&
            displayItems.length === 0
        ) {
            const msgTemplate = this.config.min_chars_message || 'Escribe al menos :count caracteres para buscar...';
            this._renderEmptyState(msgTemplate.replace(':count', String(minChars)));
            return;
        }

        if (
            this.config.searchable &&
            minChars > 0 &&
            term.length === 0 &&
            this.config.auto_fetch === false &&
            !this._hasFetchedOnce &&
            displayItems.length === 0
        ) {
            const msgTemplate = this.config.min_chars_message || 'Escribe al menos :count caracteres para buscar...';
            this._renderEmptyState(msgTemplate.replace(':count', String(minChars)));
            return;
        }

        if (displayItems.length === 0) {
            const msg = term !== ''
                ? (this.config.no_results_message || 'No se encontraron resultados')
                : (this.config.empty_message || 'No hay elementos para mostrar');
            this._renderEmptyState(msg);
            return;
        }

        displayItems.forEach((item) => {
            const row = this._createRowElement(item, mode);
            this.listElement.appendChild(row);
        });
    }

    _renderEmptyState(message) {
        if (!this.listElement) return;
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'usim-item-list-empty';
        emptyDiv.textContent = message;
        this.listElement.appendChild(emptyDiv);
    }

    _createRowElement(item, mode) {
        const itemKey = String(item.id);
        const isSelected = mode !== 'none' && this._selectedSet.has(itemKey);
        const isEditing = this._editingItemId === itemKey;
        const isDisabled = Boolean(this.config.disabled || item.disabled);
        const isEditableCrud = !isDisabled && !this.config.readonly;

        const row = document.createElement('div');
        row.className = 'usim-item-list-row';
        row.setAttribute('data-item-id', itemKey);
        row.setAttribute('role', 'option');
        row.setAttribute('aria-selected', isSelected ? 'true' : 'false');

        if (mode !== 'none' && !isDisabled && !isEditing) {
            row.classList.add('is-selectable');
            row.tabIndex = 0;
        }
        if (isSelected) {
            row.classList.add('is-selected');
        }
        if (isEditing) {
            row.classList.add('is-editing');
        }
        if (isDisabled) {
            row.classList.add('is-disabled');
        }
        if (item._isPinnedSelection) {
            row.classList.add('is-pinned-selection');
        }

        // 1. Left Selection Indicator
        if (mode === 'multiple') {
            const selWrap = document.createElement('div');
            selWrap.className = 'usim-item-list-row-selector';

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'usim-item-list-row-checkbox';
            cb.checked = isSelected;
            cb.disabled = isDisabled;
            cb.tabIndex = -1;
            cb.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!isDisabled) {
                    this._handleRowSelectToggle(item);
                }
            });

            selWrap.appendChild(cb);
            row.appendChild(selWrap);
        } else if (mode === 'single') {
            const selWrap = document.createElement('div');
            selWrap.className = 'usim-item-list-row-selector';

            const radioIndicator = document.createElement('span');
            radioIndicator.className = 'usim-item-list-row-radio';
            selWrap.appendChild(radioIndicator);
            row.appendChild(selWrap);
        }

        // 2. Optional Icon
        if (item.icon && !isEditing) {
            const iconEl = document.createElement('span');
            iconEl.className = 'usim-item-list-row-icon';
            iconEl.textContent = item.icon;
            row.appendChild(iconEl);
        }

        // 3. Center Content (or Inline Edit Input)
        const content = document.createElement('div');
        content.className = 'usim-item-list-row-content';

        if (isEditing) {
            const editBox = document.createElement('div');
            editBox.className = 'usim-item-list-inline-edit-box';
            editBox.addEventListener('click', (e) => e.stopPropagation());

            const editInput = document.createElement('input');
            editInput.type = 'text';
            editInput.className = 'usim-item-list-edit-input';
            editInput.value = item.label;
            editInput.autocomplete = 'off';

            const saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'usim-item-list-action-btn save';
            saveBtn.title = this.config.save_button_label || 'Guardar';
            saveBtn.textContent = '✓';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'usim-item-list-action-btn cancel';
            cancelBtn.title = this.config.cancel_button_label || 'Cancelar';
            cancelBtn.textContent = '✕';

            editInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    this._commitInlineEdit(item, editInput.value);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this._cancelInlineEdit();
                }
            });

            saveBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._commitInlineEdit(item, editInput.value);
            });

            cancelBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._cancelInlineEdit();
            });

            editBox.appendChild(editInput);
            editBox.appendChild(saveBtn);
            editBox.appendChild(cancelBtn);
            content.appendChild(editBox);

            setTimeout(() => {
                editInput.focus();
                editInput.select();
            }, 0);
        } else {
            const mainLine = document.createElement('div');
            mainLine.className = 'usim-item-list-row-main';

            const labelEl = document.createElement('span');
            labelEl.className = 'usim-item-list-row-label';
            labelEl.textContent = item.label;
            mainLine.appendChild(labelEl);

            if (item.badge) {
                const badgeEl = document.createElement('span');
                badgeEl.className = `usim-item-list-row-badge style-${item.badge_style || 'default'}`;
                badgeEl.textContent = item.badge;
                mainLine.appendChild(badgeEl);
            }

            if (item._isPinnedSelection) {
                const pinnedBadge = document.createElement('span');
                pinnedBadge.className = 'usim-item-list-row-badge style-pinned';
                pinnedBadge.textContent = 'Seleccionado';
                mainLine.appendChild(pinnedBadge);
            }

            content.appendChild(mainLine);

            if (item.subtitle) {
                const subEl = document.createElement('div');
                subEl.className = 'usim-item-list-row-subtitle';
                subEl.textContent = item.subtitle;
                content.appendChild(subEl);
            }
        }

        row.appendChild(content);

        // 4. Right CRUD Action Buttons (Edit & Delete)
        const canEditRow = Boolean(this.config.editable && item.editable !== false && isEditableCrud);
        const canDeleteRow = Boolean(this.config.deletable && item.deletable !== false && isEditableCrud);

        if (!isEditing && (canEditRow || canDeleteRow)) {
            const actions = document.createElement('div');
            actions.className = 'usim-item-list-row-actions';

            if (canEditRow) {
                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'usim-item-list-action-btn edit';
                editBtn.title = 'Editar';
                editBtn.setAttribute('aria-label', `Editar ${item.label}`);
                editBtn.textContent = '✏️';
                editBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this._handleRowEditClicked(item);
                });
                actions.appendChild(editBtn);
            }

            if (canDeleteRow) {
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'usim-item-list-action-btn delete';
                delBtn.title = 'Eliminar';
                delBtn.setAttribute('aria-label', `Eliminar ${item.label}`);
                delBtn.textContent = '🗑️';
                delBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this._handleRowDeleteClicked(item);
                });
                actions.appendChild(delBtn);
            }

            row.appendChild(actions);
        }

        // Row click & keyboard handlers
        if (!isEditing && !isDisabled) {
            if (mode !== 'none') {
                row.addEventListener('click', () => {
                    this._handleRowSelectToggle(item);
                });
            }

            row.addEventListener('keydown', (e) => {
                if (e.key === ' ' || e.key === 'Enter') {
                    if (mode !== 'none') {
                        e.preventDefault();
                        this._handleRowSelectToggle(item);
                    }
                } else if (e.key === 'F2' && canEditRow) {
                    e.preventDefault();
                    this._handleRowEditClicked(item);
                } else if ((e.key === 'Delete' || e.key === 'Backspace') && canDeleteRow) {
                    e.preventDefault();
                    this._handleRowDeleteClicked(item);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const next = row.nextElementSibling;
                    if (next && typeof next.focus === 'function') next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = row.previousElementSibling;
                    if (prev && typeof prev.focus === 'function') prev.focus();
                }
            });
        }

        return row;
    }

    // ─── Form Value Synchronization ───────────────────────────────────────────

    _syncFormInputs() {
        if (!this.formInputsContainer) return;
        this.formInputsContainer.innerHTML = '';

        const fieldName = this.config.name;
        if (!fieldName) return;

        const mode = this._normalizeSelectionMode(this.config.selection_mode);
        if (mode === 'none') return;

        const selectedArray = this._getSelectedArray();

        if (mode === 'multiple') {
            if (selectedArray.length === 0) {
                // Unchecked checkbox with [] ensures collectContextValues() initializes an empty array []
                const emptyInput = document.createElement('input');
                emptyInput.type = 'checkbox';
                emptyInput.name = `${fieldName}[]`;
                emptyInput.value = '';
                emptyInput.checked = false;
                this.formInputsContainer.appendChild(emptyInput);
            } else {
                selectedArray.forEach((val) => {
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = `${fieldName}[]`;
                    cb.value = String(val);
                    cb.checked = true;
                    this.formInputsContainer.appendChild(cb);
                });
            }
        } else if (mode === 'single') {
            if (selectedArray.length > 0) {
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = fieldName;
                radio.value = String(selectedArray[0]);
                radio.checked = true;
                this.formInputsContainer.appendChild(radio);
            }
        }
    }

    // ─── Async Data Fetching (GET /api/ui-data) & Loading State ───────────────

    setLoading(isLoading, options = {}) {
        const element = this.element;
        if (!element) return;

        if (isLoading && options.preserveSearchFocus) {
            element.classList.add('ui-is-loading');
            element.setAttribute('aria-busy', 'true');

            let spinner = this.bodyWrapperElement?.querySelector(':scope > .ui-component-spinner') || this._uiSpinner;
            if (!spinner && this.bodyWrapperElement) {
                spinner = document.createElement('span');
                spinner.className = 'ui-component-spinner';
                spinner.setAttribute('role', 'status');
                spinner.setAttribute('aria-hidden', 'true');
                spinner.innerHTML = '<span class="ui-spinner-icon"></span>';
                this.bodyWrapperElement.appendChild(spinner);
                this._uiSpinner = spinner;
            }
            return;
        }

        super.setLoading(isLoading, options);

        if (isLoading && this._uiSpinner && this.bodyWrapperElement) {
            this.bodyWrapperElement.appendChild(this._uiSpinner);
        }
    }

    async loadItems(overrideParams = null, options = {}) {
        if (!this.config.action) return;

        const term = this._searchTerm.trim();
        const minChars = Number(this.config.min_search_chars || 0);

        if (options.fromSearch && minChars > 0 && term.length > 0 && term.length < minChars) {
            this._renderListBody();
            this._renderHeader();
            return;
        }

        try {
            const baseParams = {
                ...(this.config.parameters || {}),
                ...(overrideParams || {}),
            };

            if (this.config.searchable) {
                baseParams.search = term;
            }

            const result = await this.fetchData(this.config.action, baseParams, {
                preserveSearchFocus: Boolean(options.fromSearch),
            });

            this._hasFetchedOnce = true;

            if (result !== null && (Array.isArray(result) || typeof result === 'object')) {
                const rawList = Array.isArray(result)
                    ? result
                    : (Array.isArray(result.items) ? result.items : result);

                this.config.items = rawList;
                this._items = this._normalizeItems(rawList);

                // Refresh cache for any currently selected items present in the new result
                this._items.forEach((item) => {
                    const key = String(item.id);
                    if (this._selectedSet.has(key)) {
                        this._selectedItemsCache.set(key, item);
                    }
                });

                this._renderHeader();
                this._renderListBody();
                this._syncFormInputs();
            }
        } catch (error) {
            console.error('Failed to load dynamic items for ItemList:', error);
        }
    }

    // ─── Interaction Handlers ─────────────────────────────────────────────────

    _onSearchInputChanged(immediate = false) {
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }

        const hasRemoteQuery = Boolean(this.config.action || this.config.on_search);
        const delay = (!immediate && hasRemoteQuery) ? Number(this.config.debounce ?? 300) : 0;

        const runSearch = () => {
            if (this.config.action) {
                this.loadItems(null, { fromSearch: true });
            } else if (this.config.on_search) {
                this._dispatchConfiguredEvent(this.config.on_search, {
                    search: this._searchTerm.trim(),
                    value: this._searchTerm.trim(),
                }, 'input');
            } else {
                this._renderHeader();
                this._renderListBody();
            }
        };

        if (delay > 0) {
            this._debounceTimer = setTimeout(runSearch, delay);
        } else {
            runSearch();
        }
    }

    _handleRowSelectToggle(item) {
        const mode = this._normalizeSelectionMode(this.config.selection_mode);
        if (mode === 'none') return;

        const key = String(item.id);
        const wasSelected = this._selectedSet.has(key);

        if (mode === 'single') {
            this._selectedSet.clear();
            this._selectedRawMap.clear();
            this._selectedItemsCache.clear();

            if (!wasSelected) {
                this._selectedSet.add(key);
                this._selectedRawMap.set(key, item.id);
                this._selectedItemsCache.set(key, item);
            }
        } else if (mode === 'multiple') {
            if (wasSelected) {
                this._selectedSet.delete(key);
                this._selectedRawMap.delete(key);
                this._selectedItemsCache.delete(key);
            } else {
                this._selectedSet.add(key);
                this._selectedRawMap.set(key, item.id);
                this._selectedItemsCache.set(key, item);
            }
        }

        this.config.selected_items = this._getSelectedArray();
        this._renderHeader();
        this._renderListBody();
        this._syncFormInputs();

        if (this.config.on_select) {
            const selected = this._getSelectedValueForMode();
            const payload = {
                id: item.id,
                selected: this._getSelectedArray(),
                value: selected,
                item,
            };
            if (this.config.name) {
                payload[this.config.name] = selected;
            }
            this._dispatchConfiguredEvent(this.config.on_select, payload, 'change');
        }
    }

    _handleSelectAllVisible(shouldSelect, visibleItems) {
        visibleItems.forEach((item) => {
            const key = String(item.id);
            if (shouldSelect) {
                this._selectedSet.add(key);
                this._selectedRawMap.set(key, item.id);
                this._selectedItemsCache.set(key, item);
            } else {
                this._selectedSet.delete(key);
                this._selectedRawMap.delete(key);
                this._selectedItemsCache.delete(key);
            }
        });

        this.config.selected_items = this._getSelectedArray();
        this._renderHeader();
        this._renderListBody();
        this._syncFormInputs();

        if (this.config.on_select) {
            const selected = this._getSelectedArray();
            const payload = {
                id: null,
                selected,
                value: selected,
            };
            if (this.config.name) {
                payload[this.config.name] = selected;
            }
            this._dispatchConfiguredEvent(this.config.on_select, payload, 'change');
        }
    }

    _handleCreateInline() {
        if (!this.createInputElement) return;
        const value = this.createInputElement.value.trim();
        if (!value) {
            this.createInputElement.focus();
            return;
        }

        this.createInputElement.value = '';
        this.createInputElement.focus();

        if (this.config.on_create) {
            this._dispatchConfiguredEvent(this.config.on_create, {
                value,
                label: value,
            }, 'action');
        }
    }

    _handleCreateModalTrigger() {
        if (this.config.on_create) {
            this._dispatchConfiguredEvent(this.config.on_create, {}, 'click');
        }
    }

    _handleRowEditClicked(item) {
        if (this.config.edit_inline !== false) {
            this._editingItemId = String(item.id);
            this._renderListBody();
        } else if (this.config.on_update) {
            this._dispatchConfiguredEvent(this.config.on_update, {
                id: item.id,
                value: item.label,
                label: item.label,
                item,
            }, 'action');
        }
    }

    _commitInlineEdit(item, rawNewValue) {
        const value = String(rawNewValue || '').trim();
        this._editingItemId = null;

        if (!value || value === item.label) {
            this._renderListBody();
            return;
        }

        // Optimistic local label update
        item.label = value;
        const key = String(item.id);
        if (this._selectedItemsCache.has(key)) {
            this._selectedItemsCache.set(key, { ...item, label: value });
        }
        this._renderListBody();

        if (this.config.on_update) {
            this._dispatchConfiguredEvent(this.config.on_update, {
                id: item.id,
                value,
                label: value,
                item,
            }, 'action');
        }
    }

    _cancelInlineEdit() {
        this._editingItemId = null;
        this._renderListBody();
    }

    _handleRowDeleteClicked(item) {
        if (this.config.on_delete) {
            this._dispatchConfiguredEvent(this.config.on_delete, {
                id: item.id,
                item,
            }, 'action');
        }
    }

    _handleBulkDelete() {
        const ids = this._getSelectedArray();
        if (ids.length === 0) return;

        if (this.config.on_bulk_delete) {
            this._dispatchConfiguredEvent(this.config.on_bulk_delete, {
                ids,
                selected: ids,
            }, 'action');
        } else if (this.config.on_delete) {
            this._dispatchConfiguredEvent(this.config.on_delete, {
                ids,
                selected: ids,
            }, 'action');
        }
    }

    async _dispatchConfiguredEvent(handlerConfig, extraParams = {}, eventType = 'action') {
        if (!handlerConfig) return;

        const action = typeof handlerConfig === 'string'
            ? handlerConfig
            : handlerConfig.action;
        const baseParams = (typeof handlerConfig === 'object' && handlerConfig.parameters)
            ? handlerConfig.parameters
            : {};

        if (!action) return;

        const parameters = {
            ...baseParams,
            ...extraParams,
        };

        await this.triggerAction(action, parameters, eventType);
    }

    _escapeHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}

window.UsimItemListComponent = UsimItemListComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('item_list');
    window.USIM_COMPONENTS.register('item_list', (id, config) => new UsimItemListComponent(id, config), {
        source: 'modular',
        description: 'Vertical generic list with CRUD, single/multiple selection, and async ui-data search',
    });

    window.USIM_COMPONENTS.unregister('itemlist');
    window.USIM_COMPONENTS.register('itemlist', (id, config) => new UsimItemListComponent(id, config), {
        source: 'modular',
    });
}
