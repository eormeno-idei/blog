/**
 * Button component (modular override)
 */
class UsimButtonComponent extends UIComponent {
    render() {
        const button = document.createElement('button');
        button.className = `ui-button ${this.config.style || 'primary'}`;
        if (this.config.no_background) button.classList.add('ui-button-no-background');
        if (this.config.no_hover) button.classList.add('ui-button-no-hover');
        this._applyContent(button);

        const isEnabled = this.config.enabled !== undefined ? this.config.enabled : true;
        button.disabled = !isEnabled;

        if (this.config.action) {
            button.addEventListener('click', () => {
                this.handleAction(this.config.action, this.config.parameters);
            });
        }

        if (this.config.tooltip) {
            button.title = this.config.tooltip;
        }

        return this.applyCommonAttributes(button);
    }

    _applyContent(button) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (helpers?.renderButtonContent) {
            helpers.renderButtonContent(button, this.config, { defaultLabel: 'Button' });
            return;
        }

        button.textContent = this.config.label || 'Button';
    }

    handleAction(action, parameters = {}) {
        const contextValues = this.collectContextValues();
        const mergedParameters = { ...contextValues, ...parameters };
        this.sendEventToBackend('click', action, mergedParameters);
    }

    collectContextValues() {
        const values = {};
        const buttonElement = document.querySelector(`[data-component-id="${this.getComponentId()}"]`);
        if (!buttonElement) {
            return values;
        }

        const modalElement = buttonElement.closest('#modal');
        let container;

        if (modalElement) {
            container = modalElement;
        } else {
            const allContainers = [];
            let currentElement = buttonElement.parentElement;

            while (currentElement && currentElement !== document.body) {
                if (currentElement.classList.contains('ui-container')) {
                    allContainers.push(currentElement);
                }
                currentElement = currentElement.parentElement;
            }

            for (const potentialContainer of allContainers) {
                const hasInputs = potentialContainer.querySelectorAll('input, textarea, select').length > 0;
                if (hasInputs) {
                    container = potentialContainer;
                    break;
                }
            }

            if (!container) {
                container = allContainers[allContainers.length - 1] || document;
            }
        }

        const inputs = container.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]), textarea');
        inputs.forEach(input => {
            if (input.name) {
                values[input.name] = input.value;
            }
        });

        const selects = container.querySelectorAll('select');
        selects.forEach(select => {
            if (select.name) {
                values[select.name] = select.value;
            }
        });

        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            if (checkbox.name) {
                if (checkbox.name.endsWith('[]')) {
                    const cleanName = checkbox.name.slice(0, -2);
                    if (!Array.isArray(values[cleanName])) {
                        values[cleanName] = [];
                    }
                    if (checkbox.checked) {
                        values[cleanName].push(checkbox.value);
                    }
                } else {
                    values[checkbox.name] = checkbox.checked;
                }
            }
        });

        const radios = container.querySelectorAll('input[type="radio"]:checked');
        radios.forEach(radio => {
            if (radio.name) {
                values[radio.name] = radio.value;
            }
        });

        return values;
    }
}

window.UsimButtonComponent = UsimButtonComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('button');
    window.USIM_COMPONENTS.register('button', (id, config) => new UsimButtonComponent(id, config), {
        source: 'modular',
    });
}
