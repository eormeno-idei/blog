/**
 * TableCell component (modular implementation)
 */
class UsimTableCellComponent extends UIComponent {
    render() {
        const cell = document.createElement('td');
        cell.className = 'ui-table-cell';

        const columnIndex = Number(this.config?.column);
        if (Number.isInteger(columnIndex) && columnIndex >= 0) {
            cell.setAttribute('data-column', String(columnIndex));
        }

        if (this.config.button) {
            const btn = document.createElement('button');
            btn.className = `ui-button ${this.config.button.style || 'default'}`;
            if (this.config.button.no_background) btn.classList.add('ui-button-no-background');
            if (this.config.button.no_hover) btn.classList.add('ui-button-no-hover');
            this.applyTableButtonContent(btn, this.config.button);

            if (this.config.button.tooltip) {
                btn.title = this.config.button.tooltip;
            }

            if (this.config.button.action) {
                btn.addEventListener('click', () => {
                    this.handleButtonClick(
                        this.config.button.action,
                        this.config.button.parameters || {}
                    );
                });
            }

            cell.appendChild(btn);
        } else if (this.config.url_image) {
            const img = document.createElement('img');
            img.src = this.config.url_image;
            img.alt = this.config.alt || '';
            img.className = 'ui-table-cell-image';
            if (this.config.image_width) img.style.width = this.config.image_width;
            if (this.config.image_height) img.style.height = this.config.image_height;
            cell.appendChild(img);
        } else if (this.config.text !== undefined && this.config.text !== null) {
            cell.textContent = this.config.text;
        }

        if (this.config.align) {
            cell.style.textAlign = this.config.align;
        }

        if (this.config.min_height !== undefined && this.config.min_height !== null && this.config.min_height !== '') {
            const minHeight = typeof this.config.min_height === 'number'
                ? `${this.config.min_height}px`
                : this.config.min_height;

            // In table layouts, height is often treated as the effective minimum.
            cell.style.height = minHeight;
            cell.style.minHeight = minHeight;
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

    applyTableButtonContent(button, buttonConfig) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (helpers?.renderButtonContent) {
            helpers.renderButtonContent(button, buttonConfig, {
                defaultLabel: 'Action',
                forceImageIcon: true,
            });
            return;
        }

        button.textContent = buttonConfig.label || 'Action';
    }

    async handleButtonClick(action, parameters) {
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
            console.error('Error executing cell button action:', error);
        }
    }
}

window.UsimTableCellComponent = UsimTableCellComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('tablecell');
    window.USIM_COMPONENTS.register('tablecell', (id, config) => new UsimTableCellComponent(id, config), {
        source: 'modular',
    });
}
