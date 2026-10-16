/**
 * Table component (modular implementation)
 */
class UsimTableComponent extends UIComponent {
    scheduleSelectedRowsStateSync() {
        if (this._selectedRowsSyncScheduled) {
            return;
        }

        this._selectedRowsSyncScheduled = true;
        queueMicrotask(() => {
            this._selectedRowsSyncScheduled = false;
            this.applySelectedRowsState();
        });
    }

    normalizeRenderedRows() {
        const rowHost = this.tbodyElement || this.tableElement;
        if (!rowHost) {
            return;
        }

        const expectedCols = Number(this.config?.cols ?? 0);
        if (!Number.isInteger(expectedCols) || expectedCols <= 0) {
            return;
        }

        const rows = Array.from(rowHost.querySelectorAll('.ui-table-row'));
        for (const row of rows) {
            const cells = Array.from(row.querySelectorAll(':scope > .ui-table-cell'));

            // Cells from the initial render path (mount) don't have data-column set.
            // Backfill using DOM order so the column-deduplication logic below works correctly
            // and doesn't treat every untagged cell as column 0 (destroying real cell content).
            const anyMissingColumn = cells.some((c) => c.getAttribute('data-column') === null);
            if (anyMissingColumn) {
                cells.forEach((cell, idx) => cell.setAttribute('data-column', String(idx)));
            }

            const byColumn = new Map();
            const overflow = [];

            for (const cell of cells) {
                const columnAttr = cell.getAttribute('data-column');
                const column = Number(columnAttr);

                if (Number.isInteger(column) && column >= 0 && column < expectedCols && !byColumn.has(column)) {
                    byColumn.set(column, cell);
                    continue;
                }

                overflow.push(cell);
            }

            overflow.forEach((cell) => cell.remove());

            for (let col = 0; col < expectedCols; col++) {
                let cell = byColumn.get(col);
                if (!cell) {
                    cell = document.createElement('td');
                    cell.className = 'ui-table-cell ui-table-cell-placeholder';
                    cell.setAttribute('data-column', String(col));
                    byColumn.set(col, cell);
                }

                row.appendChild(cell);
            }
        }
    }

    syncRenderedRows() {
        const rowHost = this.tbodyElement || this.tableElement;
        if (!rowHost) {
            return;
        }

        const expectedRows = Number(this.config?.rows ?? 0);
        if (!Number.isFinite(expectedRows) || expectedRows < 0) {
            return;
        }

        const dataRows = Array.from(rowHost.querySelectorAll('.ui-table-row'));

        // Remove stale rows from the end so filtered tables reflect server row count immediately.
        for (let i = dataRows.length - 1; i >= expectedRows; i--) {
            dataRows[i]?.remove();
        }
    }

    update(newConfig) {
        this.config = {
            ...this.config,
            ...newConfig,
        };

        if (!this.element) {
            return;
        }

        this.applyCommonAttributes(this.element);
        this.applyBodyViewportStyles();
        this.syncRenderedRows();
        this.normalizeRenderedRows();
        this.scheduleSelectedRowsStateSync();
        const shouldRenderPagination = this.shouldRenderPaginationControls();
        this.upsertPaginationControls(shouldRenderPagination);
    }

    shouldRenderPaginationControls() {
        const pagination = this.config?.pagination;
        if (!pagination || pagination.enabled === false) {
            return false;
        }

        if (pagination.show_controls === false) {
            return false;
        }

        const totalPages = Number(pagination.total_pages || 0);
        if (totalPages > 0) {
            return totalPages > 1;
        }

        const perPage = Number(pagination.per_page || 0);
        const totalItems = Number(pagination.total_items || 0);
        if (perPage <= 0) {
            return false;
        }

        return Math.ceil(totalItems / perPage) > 1;
    }

    render() {
        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'ui-table-wrapper';

        if (this.config.align) {
            tableWrapper.classList.add(`align-${this.config.align}`);
        }

        if (this.config.title) {
            const title = document.createElement('h3');
            title.className = 'ui-table-title';
            title.textContent = this.config.title;
            tableWrapper.appendChild(title);
        }

        const table = document.createElement('table');
        table.className = 'ui-table';

        const thead = document.createElement('thead');
        thead.className = 'ui-table-head';
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        tbody.className = 'ui-table-body';
        table.appendChild(tbody);

        const tfoot = document.createElement('tfoot');
        tfoot.className = 'ui-table-foot';
        table.appendChild(tfoot);

        tableWrapper.appendChild(table);

        this.tableElement = table;
        this.theadElement = thead;
        this.tbodyElement = tbody;
        this.tfootElement = tfoot;
        this.applyBodyViewportStyles();
        this.upsertPaginationControls(this.shouldRenderPaginationControls());

        this.element = this.applyCommonAttributes(tableWrapper);
        return this.element;
    }

    getChildMountTarget(childConfig, childComponent) {
        if (!this.tableElement || !childConfig) {
            return this.tableElement || this.element;
        }

        if (childConfig.type === 'tableheaderrow') {
            return this.theadElement || this.tableElement;
        }

        if (childConfig.type === 'tablerow') {
            return this.tbodyElement || this.tableElement;
        }

        if (childConfig.type === 'container') {
            const rowsContainerId = String(this.config?.rows_container ?? '');
            const childId = String(childComponent?.id ?? childConfig.id ?? '');
            if (rowsContainerId && childId && rowsContainerId === childId) {
                return this.tbodyElement || this.tableElement;
            }
        }

        return this.tableElement;
    }

    applyBodyViewportStyles() {
        if (!this.tbodyElement || !this.tableElement) {
            return;
        }

        const minHeight = this.normalizeSize(this.config?.body_min_height);
        const maxHeight = this.normalizeSize(this.config?.body_max_height);
        const overflowX = this.normalizeOverflow(this.config?.body_overflow_x);
        const overflowY = this.normalizeOverflow(this.config?.body_overflow_y);
        const viewportEnabled = Boolean(minHeight || maxHeight || overflowX !== 'visible' || overflowY !== 'visible');

        this.tableElement.classList.toggle('ui-table-body-viewport', viewportEnabled);

        this.tbodyElement.style.minHeight = minHeight || '';
        this.tbodyElement.style.maxHeight = maxHeight || '';
        this.tbodyElement.style.overflowX = overflowX || '';
        this.tbodyElement.style.overflowY = overflowY || '';

        const rowHeight = this.normalizeSize(this.config?.row_height || this.config?.row_min_height);
        if (rowHeight) {
            this.tableElement.style.setProperty('--ui-table-row-height', rowHeight);
            if (this.element) {
                this.element.style.setProperty('--ui-table-row-height', rowHeight);
            }
        }
    }

    normalizeSize(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        if (typeof value === 'number') {
            return `${value}px`;
        }

        const str = String(value).trim();
        return /^\d+$/.test(str) ? `${str}px` : str;
    }

    normalizeOverflow(value) {
        const normalized = String(value || 'visible').toLowerCase().trim();
        const allowed = new Set(['visible', 'hidden', 'auto', 'scroll']);

        return allowed.has(normalized) ? normalized : 'visible';
    }

    normalizeSelectionMode(value) {
        const normalized = String(value || 'none').toLowerCase().trim();
        if (normalized === 'single' || normalized === 'multiple') {
            return normalized;
        }

        return 'none';
    }

    getSelectedRowIdSet() {
        const selectionMode = this.normalizeSelectionMode(this.config?.selection_mode);
        const rawSelection = this.config?.selected_rows;
        let selectedRows = [];

        if (Array.isArray(rawSelection)) {
            selectedRows = rawSelection;
        } else if (rawSelection !== null && rawSelection !== undefined && rawSelection !== '') {
            selectedRows = [rawSelection];
        }

        if (selectionMode === 'single' && selectedRows.length > 1) {
            selectedRows = [selectedRows[0]];
        }

        return new Set(selectedRows.map((value) => String(value)));
    }

    applySelectedRowsState() {
        const rowHost = this.tbodyElement || this.tableElement;
        if (!rowHost) {
            return;
        }

        const rows = Array.from(rowHost.querySelectorAll('.ui-table-row'));
        if (rows.length === 0) {
            return;
        }

        const selectionMode = this.normalizeSelectionMode(this.config?.selection_mode);
        if (selectionMode === 'none') {
            rows.forEach((row) => row.classList.remove('selected'));
            return;
        }

        const selectedIds = this.getSelectedRowIdSet();
        rows.forEach((row) => {
            const modelId = row.getAttribute('data-row-model-id');
            if (modelId === null) {
                return;
            }

            row.classList.toggle('selected', selectedIds.has(String(modelId)));
        });
    }

    ensureFooterCell() {
        if (!this.tfootElement) {
            return null;
        }

        this.tfootElement.innerHTML = '';

        const footerRow = document.createElement('tr');
        footerRow.className = 'ui-table-footer-row';

        const footerCell = document.createElement('td');
        footerCell.className = 'ui-table-footer-cell';
        footerCell.colSpan = Math.max(1, Number(this.config?.cols || 1));

        footerRow.appendChild(footerCell);
        this.tfootElement.appendChild(footerRow);

        return footerCell;
    }

    upsertPaginationControls(shouldRender) {
        const oldPagination = this.element?.querySelector('.ui-pagination');

        if (!shouldRender) {
            if (oldPagination) {
                oldPagination.remove();
            }

            if (this.tfootElement) {
                this.tfootElement.innerHTML = '';
            }

            return;
        }

        const footerCell = this.ensureFooterCell();
        if (!footerCell) {
            return;
        }

        const newPagination = this.createPaginationControls();
        footerCell.appendChild(newPagination);
    }

    createPaginationControls() {
        const paginationDiv = document.createElement('div');
        paginationDiv.className = 'ui-pagination';
        paginationDiv.setAttribute('data-component-id', this.id);

        const pagination = this.config.pagination || {};
        const currentPage = pagination.current_page || 1;
        const perPage = pagination.per_page || 10;
        const totalItems = pagination.total_items || 0;
        const totalPages = pagination.total_pages || 1;
        const canNext = pagination.can_next !== undefined ? pagination.can_next : (currentPage < totalPages);
        const canPrev = pagination.can_prev !== undefined ? pagination.can_prev : (currentPage > 1);

        const labels = pagination.labels || {};
        const labelPrevious = labels.previous || '\u00ab Previous';
        const labelNext = labels.next || 'Next \u00bb';
        const labelShowing = labels.showing || 'Showing :start-:end of :total items';

        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, totalItems);
        const infoDiv = document.createElement('div');
        infoDiv.className = 'ui-pagination-info';
        infoDiv.textContent = labelShowing
            .replace(':start', start)
            .replace(':end', end)
            .replace(':total', totalItems);
        paginationDiv.appendChild(infoDiv);

        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'ui-pagination-controls';

        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'ui-pagination-loading';
        loadingDiv.style.display = 'none';
        loadingDiv.style.marginLeft = '16px';
        loadingDiv.style.alignItems = 'center';
        loadingDiv.style.gap = '8px';
        loadingDiv.innerHTML = `
            <span class="spinner" style="
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid var(--usim-pagination-spinner-track, rgba(15, 23, 36, 0.12));
                border-top: 2px solid var(--usim-pagination-spinner-indicator, var(--usim-color-primary, #3498db));
                border-radius: 50%;
                animation: spin 1s linear infinite;
            "></span>
        `;

        if (!document.querySelector('#pagination-spinner-style')) {
            const style = document.createElement('style');
            style.id = 'pagination-spinner-style';
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }

        controlsDiv.appendChild(loadingDiv);
        loadingDiv.style.display = 'none';
        controlsDiv.paginationLoading = loadingDiv;

        const prevBtn = document.createElement('button');
        prevBtn.className = 'ui-pagination-button';
        prevBtn.type = 'button';
        prevBtn.textContent = labelPrevious;
        prevBtn.disabled = !canPrev;
        prevBtn.addEventListener('click', (event) => {
            event.preventDefault();
            this.changePage(currentPage - 1, paginationDiv);
        });
        controlsDiv.appendChild(prevBtn);

        const pages = this.getPageNumbers(currentPage, totalPages);
        pages.forEach(page => {
            if (page === '...') {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.style.padding = '0 8px';
                controlsDiv.appendChild(ellipsis);
            } else {
                const pageBtn = document.createElement('button');
                pageBtn.className = 'ui-pagination-button';
                pageBtn.type = 'button';
                if (page === currentPage) {
                    pageBtn.classList.add('active');
                }
                pageBtn.textContent = page;
                pageBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.changePage(page, paginationDiv);
                });
                controlsDiv.appendChild(pageBtn);
            }
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'ui-pagination-button';
        nextBtn.type = 'button';
        nextBtn.textContent = labelNext;
        nextBtn.disabled = !canNext;
        nextBtn.addEventListener('click', (event) => {
            event.preventDefault();
            this.changePage(currentPage + 1, paginationDiv);
        });
        controlsDiv.appendChild(nextBtn);

        paginationDiv.appendChild(controlsDiv);

        return paginationDiv;
    }

    getPageNumbers(current, total) {
        const pages = [];
        const maxVisible = 5;

        if (total <= maxVisible + 2) {
            for (let i = 1; i <= total; i++) {
                pages.push(i);
            }
        } else {
            pages.push(1);

            if (current > 3) {
                pages.push('...');
            }

            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            if (current < total - 2) {
                pages.push('...');
            }

            pages.push(total);
        }

        return pages;
    }

    async changePage(page, paginationDiv = null) {
        if (!paginationDiv) {
            paginationDiv = this.element?.querySelector('.ui-pagination');
        }

        if (paginationDiv) {
            this.setLoadingState(paginationDiv, true);
        }

        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, response, result } = await helpers.sendUiEvent({
                componentId,
                event: 'action',
                action: 'change_page',
                parameters: { page },
                credentials: 'same-origin',
            });

            if (!ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            if (result) {
                const tableData = result[this.id];

                if (tableData && tableData.pagination) {
                    this.config.pagination = tableData.pagination;
                    const shouldRenderPagination = this.shouldRenderPaginationControls();
                    this.upsertPaginationControls(shouldRenderPagination);
                } else {
                    if (paginationDiv) {
                        this.setLoadingState(paginationDiv, false);
                    }
                }

                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Error changing page:', error);

            if (paginationDiv) {
                this.setLoadingState(paginationDiv, false);
            }
        }
    }

    setLoadingState(paginationDiv, isLoading) {
        const controlsDiv = paginationDiv.querySelector('.ui-pagination-controls');
        if (!controlsDiv) return;

        const buttons = controlsDiv.querySelectorAll('button');
        const loadingDiv = controlsDiv.querySelector('.ui-pagination-loading');

        if (isLoading) {
            buttons.forEach(btn => btn.disabled = true);
            if (loadingDiv) {
                loadingDiv.style.display = 'flex';
            }
        } else {
            const pagination = this.config.pagination || {};
            const currentPage = pagination.current_page || 1;
            const totalPages = pagination.total_pages || 1;
            const canNext = pagination.can_next !== undefined ? pagination.can_next : (currentPage < totalPages);
            const canPrev = pagination.can_prev !== undefined ? pagination.can_prev : (currentPage > 1);

            buttons.forEach((btn) => {
                const btnText = btn.textContent.trim();

                if (btnText === '« Previous') {
                    btn.disabled = !canPrev;
                } else if (btnText === 'Next »') {
                    btn.disabled = !canNext;
                } else if (!isNaN(btnText)) {
                    btn.disabled = false;
                }
            });

            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
        }
    }

    mount(parentElement) {
        super.mount(parentElement);
        this.syncRenderedRows();
        this.normalizeRenderedRows();
        this.scheduleSelectedRowsStateSync();
    }
}

window.UsimTableComponent = UsimTableComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('table');
    window.USIM_COMPONENTS.register('table', (id, config) => new UsimTableComponent(id, config), {
        source: 'modular',
    });
}
