/**
 * Checkbox component (modular override)
 */
class UsimCheckboxComponent extends UIComponent {
    render() {
        const hasOptions = Array.isArray(this.config.options) && this.config.options.length > 0;

        if (hasOptions) {
            const container = document.createElement('div');
            const layout = this.config.layout || 'vertical';
            container.className = `ui-checkbox-group-wrapper ui-checkbox-group-${layout}`;

            if (this.config.label) {
                const groupLabel = document.createElement('label');
                groupLabel.className = 'ui-checkbox-group-title';
                groupLabel.textContent = this.config.label;
                if (this.config.required) {
                    groupLabel.classList.add('required');
                }
                groupLabel.style.display = 'block';
                groupLabel.style.marginBottom = '8px';
                groupLabel.style.fontWeight = '500';
                container.appendChild(groupLabel);
            }

            const itemsWrapper = document.createElement('div');
            itemsWrapper.className = `ui-checkbox-items ui-checkbox-items-${layout}`;
            if (layout === 'vertical') {
                itemsWrapper.style.display = 'flex';
                itemsWrapper.style.flexDirection = 'column';
                itemsWrapper.style.gap = '8px';
            } else if (layout === 'horizontal') {
                itemsWrapper.style.display = 'flex';
                itemsWrapper.style.flexDirection = 'row';
                itemsWrapper.style.flexWrap = 'wrap';
                itemsWrapper.style.gap = '15px';
            }

            const selectedValues = Array.isArray(this.config.selected_values)
                ? this.config.selected_values
                : (Array.isArray(this.config.value) ? this.config.value : (this.config.value ? [this.config.value] : []));

            this.config.options.forEach((option, index) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'ui-checkbox-item';
                itemDiv.style.display = 'flex';
                itemDiv.style.alignItems = 'center';
                itemDiv.style.gap = '8px';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'ui-checkbox';
                checkbox.name = `${this.config.name}[]`;
                checkbox.id = `${this.config.name}_${option.value || index}`;
                checkbox.value = option.value;
                checkbox.checked = selectedValues.includes(option.value);
                checkbox.disabled = this.config.disabled || option.disabled || false;

                if (this.config.on_change) {
                    checkbox.addEventListener('change', async (e) => {
                        await this.handleChange(this.config.on_change, e.target.checked, option.value);
                    });
                }

                itemDiv.appendChild(checkbox);

                const itemLabel = document.createElement('label');
                itemLabel.className = 'ui-checkbox-label';
                itemLabel.textContent = option.label || option.value;
                itemLabel.setAttribute('for', checkbox.id);
                itemDiv.appendChild(itemLabel);

                itemsWrapper.appendChild(itemDiv);
            });

            container.appendChild(itemsWrapper);
            return this.applyCommonAttributes(container);
        }

        const group = document.createElement('div');
        group.className = 'ui-checkbox-group';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'ui-checkbox';
        checkbox.checked = this.config.checked || false;
        checkbox.required = this.config.required || false;
        checkbox.disabled = this.config.disabled || false;

        if (this.config.name) {
            checkbox.name = this.config.name;
            checkbox.id = this.config.name;
        }

        if (this.config.value) {
            checkbox.value = this.config.value;
        }

        if (this.config.on_change) {
            checkbox.addEventListener('change', async (e) => {
                const newCheckedState = e.target.checked;
                e.target.checked = this.config.checked || false;
                await this.handleChange(this.config.on_change, newCheckedState);
            });
        }

        group.appendChild(checkbox);

        if (this.config.label) {
            const label = document.createElement('label');
            label.className = 'ui-checkbox-label';
            label.textContent = this.config.label;
            if (this.config.required) {
                label.classList.add('required');
            }
            if (this.config.name) {
                label.setAttribute('for', this.config.name);
            }
            group.appendChild(label);
        }

        return this.applyCommonAttributes(group);
    }

    async handleChange(action, checked, value = null) {
        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, result } = await helpers.sendUiEvent({
                componentId,
                event: 'change',
                action,
                parameters: {
                    checked,
                    value,
                    name: this.config.name,
                },
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            } else {
                const checkbox = document.querySelector(`[data-component-id="${componentId}"] input[type="checkbox"]`);
                if (checkbox) {
                    checkbox.checked = this.config.checked || false;
                }
            }
        } catch (error) {
            console.error('Checkbox action error:', error);
            const checkbox = document.querySelector(`[data-component-id="${this.getComponentId()}"] input[type="checkbox"]`);
            if (checkbox) {
                checkbox.checked = this.config.checked || false;
            }
        }
    }
}

window.UsimCheckboxComponent = UsimCheckboxComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('checkbox');
    window.USIM_COMPONENTS.register('checkbox', (id, config) => new UsimCheckboxComponent(id, config), {
        source: 'modular',
    });
}

