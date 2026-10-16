/**
 * TableRow component (modular implementation)
 */
class UsimTableRowComponent extends UIComponent {
    render() {
        const row = document.createElement('tr');
        row.className = 'ui-table-row';

        this.applyInteractiveState(row);
        this.applyModelIdentifierState(row);

        if (this.config.selected) {
            row.classList.add('selected');
        }

        if (this.config.style) {
            row.classList.add(this.config.style);
        }

        const rawHeight = this.config.height ?? this.config.min_height;
        if (rawHeight !== undefined && rawHeight !== null && rawHeight !== '') {
            const minHeight = typeof rawHeight === 'number'
                ? `${rawHeight}px`
                : (typeof rawHeight === 'string' && /^\d+$/.test(rawHeight.trim()) ? `${rawHeight.trim()}px` : String(rawHeight));

            row.style.minHeight = minHeight;
            row.style.height = minHeight;
            row.style.setProperty('--ui-table-row-height', minHeight);
            row.setAttribute('data-min-height', minHeight);
        }

        this.element = this.applyCommonAttributes(row);
        return this.element;
    }

    update(newConfig) {
        this.config = {
            ...this.config,
            ...newConfig,
        };

        if (!this.element) {
            return;
        }

        this.element.className = 'ui-table-row';
        this.applyInteractiveState(this.element);
        this.applyModelIdentifierState(this.element);

        if (this.config.selected) {
            this.element.classList.add('selected');
        }

        if (this.config.style) {
            this.element.classList.add(this.config.style);
        }

        const rawHeight = this.config.height ?? this.config.min_height;
        if (rawHeight !== undefined && rawHeight !== null && rawHeight !== '') {
            const minHeight = typeof rawHeight === 'number'
                ? `${rawHeight}px`
                : (typeof rawHeight === 'string' && /^\d+$/.test(rawHeight.trim()) ? `${rawHeight.trim()}px` : String(rawHeight));

            this.element.style.minHeight = minHeight;
            this.element.style.height = minHeight;
            this.element.style.setProperty('--ui-table-row-height', minHeight);
            this.element.setAttribute('data-min-height', minHeight);
        } else {
            this.element.style.minHeight = '';
            this.element.style.height = '';
            this.element.style.removeProperty('--ui-table-row-height');
            this.element.removeAttribute('data-min-height');
        }

        this.applyCommonAttributes(this.element);
    }

    applyModelIdentifierState(row) {
        const modelId = this.config?.parameters?.model_id;

        if (modelId === null || modelId === undefined || modelId === '') {
            row.removeAttribute('data-row-model-id');
            return;
        }

        row.setAttribute('data-row-model-id', String(modelId));
    }

    applyInteractiveState(row) {
        row.classList.remove('clickable', 'pending');
        row.style.cursor = '';
        row.removeAttribute('role');
        row.removeAttribute('tabindex');
        row.removeAttribute('aria-busy');
        row.onclick = null;
        row.onkeydown = null;

        if (!this.config.action) {
            return;
        }

        row.classList.add('clickable');
        row.style.cursor = 'pointer';
        row.setAttribute('role', 'button');
        row.setAttribute('tabindex', '0');

        row.onclick = () => {
            this.handleRowClick(row);
        };

        row.onkeydown = (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.handleRowClick(row);
        };
    }

    async handleRowClick(row) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (!helpers?.sendUiEvent) {
            return;
        }

        row.classList.add('pending');
        row.setAttribute('aria-busy', 'true');

        try {
            const { ok, result } = await helpers.sendUiEvent({
                componentId: this.getComponentId(),
                event: 'click',
                action: this.config.action,
                parameters: this.config.parameters || {},
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Error handling table row click:', error);
        } finally {
            row.classList.remove('pending');
            row.removeAttribute('aria-busy');
        }
    }
}

window.UsimTableRowComponent = UsimTableRowComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('tablerow');
    window.USIM_COMPONENTS.register('tablerow', (id, config) => new UsimTableRowComponent(id, config), {
        source: 'modular',
    });
}
