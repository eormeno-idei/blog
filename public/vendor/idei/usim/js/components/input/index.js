/**
 * Input component (modular override)
 */
class UsimInputComponent extends UIComponent {
    render() {
        const group = document.createElement('div');
        group.className = 'ui-input-group';

        if (this.config.label || this.config.help_text) {
            const labelRow = document.createElement('div');
            labelRow.className = 'ui-input-label-row';

            if (this.config.label) {
                const label = document.createElement('label');
                label.textContent = this.config.label;
                if (this.config.required) {
                    label.className = 'required';
                }
                if (this.config.name) {
                    label.setAttribute('for', this.config.name);
                }
                labelRow.appendChild(label);
            }

            if (this.config.help_text) {
                const helpIcon = document.createElement('span');
                helpIcon.className = 'ui-input-help-icon';
                helpIcon.textContent = '?';
                helpIcon.setAttribute('role', 'img');
                helpIcon.setAttribute('aria-label', 'Ayuda');
                helpIcon.setAttribute('data-tooltip', this.config.help_text);
                labelRow.appendChild(helpIcon);
            }

            group.appendChild(labelRow);
        }

        const inputWrapper = document.createElement('div');
        inputWrapper.className = 'ui-input-wrapper';

        const input = document.createElement('input');
        input.className = 'ui-input';

        if (this.config.error) {
            input.classList.add('ui-input-error');
        }

        input.type = this.config.input_type || 'text';
        input.placeholder = this.config.placeholder || '';
        input.value = this.config.value || '';
        input.required = this.config.required || false;
        input.disabled = this.config.disabled || false;
        input.readonly = this.config.readonly || false;

        if (this.config.autocomplete) {
            input.setAttribute('autocomplete', this.config.autocomplete);
        }

        if (this.config.name) {
            input.name = this.config.name;
            input.id = this.config.name;
        }

        if (this.config.maxlength) input.maxLength = this.config.maxlength;
        if (this.config.minlength) input.minLength = this.config.minlength;
        if (this.config.pattern) input.pattern = this.config.pattern;

        this.attachInputEvents(input);
        inputWrapper.appendChild(input);

        if (this.config.error) {
            const errorIcon = document.createElement('span');
            errorIcon.className = 'ui-input-error-icon';
            errorIcon.innerHTML = '⚠️';
            errorIcon.setAttribute('data-tooltip', this.config.error);
            inputWrapper.appendChild(errorIcon);
        }

        group.appendChild(inputWrapper);
        this.element = group;
        return this.applyCommonAttributes(group);
    }

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };
        if (!this.element) return;

        const input = this.element.querySelector('input');
        const inputWrapper = this.element.querySelector('.ui-input-wrapper');

        if (input && newConfig.value !== undefined) {
            input.value = this.config.value ?? '';
        }

        if (input && newConfig.disabled !== undefined) {
            input.disabled = !!this.config.disabled;
        }

        if (newConfig.error !== undefined && inputWrapper && input) {
            const existingIcon = inputWrapper.querySelector('.ui-input-error-icon');
            if (existingIcon) {
                existingIcon.remove();
            }

            if (this.config.error) {
                input.classList.add('ui-input-error');
                const errorIcon = document.createElement('span');
                errorIcon.className = 'ui-input-error-icon';
                errorIcon.innerHTML = '⚠️';
                errorIcon.setAttribute('data-tooltip', this.config.error);
                inputWrapper.appendChild(errorIcon);
            } else {
                input.classList.remove('ui-input-error');
            }
        }

        if (newConfig.help_text !== undefined) {
            let labelRow = this.element.querySelector('.ui-input-label-row');
            let helpIcon = this.element.querySelector('.ui-input-help-icon');

            if (this.config.help_text) {
                if (!helpIcon) {
                    if (!labelRow) {
                        labelRow = document.createElement('div');
                        labelRow.className = 'ui-input-label-row';
                        const label = this.element.querySelector('label');
                        if (label) {
                            label.parentNode.insertBefore(labelRow, label);
                            labelRow.appendChild(label);
                        } else {
                            this.element.insertBefore(labelRow, this.element.firstChild);
                        }
                    }
                    helpIcon = document.createElement('span');
                    helpIcon.className = 'ui-input-help-icon';
                    helpIcon.textContent = '?';
                    helpIcon.setAttribute('role', 'img');
                    helpIcon.setAttribute('aria-label', 'Ayuda');
                    labelRow.appendChild(helpIcon);
                }
                helpIcon.setAttribute('data-tooltip', this.config.help_text);
            } else if (helpIcon) {
                helpIcon.remove();
            }
        }
    }

    attachInputEvents(input) {
        if (this.config.on_input) {
            const debounceTime = this.config.debounce || 0;
            let debounceTimer = null;

            input.addEventListener('input', (e) => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                debounceTimer = setTimeout(() => {
                    this.triggerAction(
                        this.config.on_input.action,
                        this.buildActionParameters(this.config.on_input.parameters, e.target.value)
                    );
                }, debounceTime);
            });
        }

        if (this.config.on_change) {
            input.addEventListener('change', (e) => {
                this.triggerAction(
                    this.config.on_change.action,
                    this.buildActionParameters(this.config.on_change.parameters, e.target.value)
                );
            });
        }

        if (this.config.on_enter) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.triggerAction(
                        this.config.on_enter.action,
                        this.buildActionParameters(this.config.on_enter.parameters, e.target.value)
                    );
                }
            });
        }
    }

    buildActionParameters(baseParameters = {}, currentValue = '') {
        const params = {
            ...(baseParameters || {}),
            value: currentValue,
        };

        if (this.config.name) {
            params[this.config.name] = currentValue;
            params.name = this.config.name;
        }

        return params;
    }

    async triggerAction(action, parameters) {
        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, result } = await helpers.sendUiEvent({
                componentId,
                event: 'input',
                action,
                parameters,
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Input action error:', error);
        }
    }
}

window.UsimInputComponent = UsimInputComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('input');
    window.USIM_COMPONENTS.register('input', (id, config) => new UsimInputComponent(id, config), {
        source: 'modular',
    });
}
