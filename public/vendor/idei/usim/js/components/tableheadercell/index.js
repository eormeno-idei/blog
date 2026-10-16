/**
 * TableHeaderCell component (modular implementation)
 */
class UsimTableHeaderCellComponent extends UIComponent {
    render() {
        const cell = document.createElement('th');
        cell.className = 'ui-table-header-cell';

        if (this.config.text !== undefined) {
            cell.textContent = this.config.text;
        }

        if (this.config.align) {
            cell.style.textAlign = this.config.align;
        }

        if (this.config.action) {
            cell.style.cursor = 'pointer';
            cell.style.userSelect = 'none';
            cell.style.transition = 'background-color 0.2s ease, opacity 0.2s ease';
            cell.style.opacity = '0.7';

            cell.addEventListener('click', () => {
                this.handleHeaderClick(this.config.action, this.config.parameters || {});
            });

            cell.addEventListener('mouseenter', () => {
                cell.style.backgroundColor = '#1976d2';
                cell.style.color = '#ffffff';
                cell.style.opacity = '1';
            });
            cell.addEventListener('mouseleave', () => {
                cell.style.backgroundColor = '';
                cell.style.color = '';
                cell.style.opacity = '0.7';
            });
        }

        if (this.config.min_width === 0 && this.config.max_width === 0) {
            cell.style.width = '0';
            cell.style.maxWidth = '0';
            cell.style.minWidth = '0';
            cell.style.padding = '0';
            cell.style.overflow = 'hidden';
            cell.style.border = 'none';
        } else if (this.config.min_width || this.config.max_width) {
            const minWidth = this.config.min_width ?? this.config.max_width;
            const maxWidth = this.config.max_width ?? this.config.min_width;
            const targetWidth = minWidth ?? maxWidth;

            if (targetWidth !== undefined && targetWidth !== null) {
                cell.style.width = `${targetWidth}px`;
            }
            if (minWidth !== undefined && minWidth !== null) {
                cell.style.minWidth = `${minWidth}px`;
            }
            if (maxWidth !== undefined && maxWidth !== null) {
                cell.style.maxWidth = `${maxWidth}px`;
            }
        }

        this.element = this.applyCommonAttributes(cell);
        return this.element;
    }

    async handleHeaderClick(action, parameters) {
        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, result } = await helpers.sendUiEvent({
                componentId,
                event: 'click',
                action,
                parameters,
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Error handling header click:', error);
        }
    }
}

window.UsimTableHeaderCellComponent = UsimTableHeaderCellComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('tableheadercell');
    window.USIM_COMPONENTS.register('tableheadercell', (id, config) => new UsimTableHeaderCellComponent(id, config), {
        source: 'modular',
    });
}
