/**
 * Select component (modular override)
 *
 * Supports:
 * - Static options (options array or key-value object)
 * - Dynamic asynchronous data fetching via action (GET /api/ui-data)
 * - Automatic loading spinner and disabled state via UIComponent::fetchData
 * - Dynamic options updates via delta diffs (update method)
 * - Single and multiple selections
 * - Change events dispatching to backend
 */
class UsimSelectComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this.selectElement = null;
        this.wrapperElement = null;
        this.groupElement = null;
    }

    render() {
        const group = document.createElement('div');
        group.className = 'ui-select-group';
        this.groupElement = group;

        if (this.config.label) {
            const label = document.createElement('label');
            label.textContent = this.config.label;
            if (this.config.required) {
                label.className = 'required';
            }
            if (this.config.name) {
                label.setAttribute('for', this.config.name);
            }
            group.appendChild(label);
        }

        const select = document.createElement('select');
        select.className = 'ui-select';
        select.required = this.config.required || false;
        select.disabled = this.config.disabled || false;

        if (this.config.multiple) {
            select.multiple = true;
            if (this.config.max_selections) {
                select.setAttribute('data-max-selections', String(this.config.max_selections));
            }
        }

        if (this.config.name) {
            select.name = this.config.name;
            select.id = this.config.name;
        }

        this.selectElement = select;

        this.renderOptions(this.config.options || []);

        const wrapper = document.createElement('div');
        wrapper.className = 'ui-select-wrapper';
        this.wrapperElement = wrapper;
        wrapper.appendChild(select);

        group.appendChild(wrapper);

        if (this.config.on_change) {
            select.addEventListener('change', () => {
                let value = select.value;
                if (select.multiple) {
                    value = Array.from(select.selectedOptions).map(opt => opt.value);
                }
                this.handleChange(this.config.on_change, value);
            });
        }

        this.applyCommonAttributes(group);

        // Fetch dynamic options if action is configured
        if (this.config.action && this.config.auto_fetch !== false) {
            setTimeout(() => {
                this.loadOptions();
            }, 0);
        }

        return group;
    }

    renderOptions(options) {
        if (!this.selectElement) return;

        this.selectElement.innerHTML = '';

        if (this.config.placeholder && !this.config.multiple) {
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = this.config.placeholder;
            placeholderOption.disabled = true;
            if (this.config.value === null || this.config.value === undefined || this.config.value === '') {
                placeholderOption.selected = true;
            }
            this.selectElement.appendChild(placeholderOption);
        }

        const isSelected = (val) => {
            if (this.config.value === null || this.config.value === undefined) return false;
            if (Array.isArray(this.config.value)) {
                return this.config.value.some(v => String(v) === String(val));
            }
            return String(this.config.value) === String(val);
        };

        if (Array.isArray(options)) {
            options.forEach(opt => {
                const option = document.createElement('option');
                if (typeof opt === 'object' && opt !== null) {
                    option.value = opt.value !== undefined ? String(opt.value) : '';
                    option.textContent = opt.label !== undefined ? String(opt.label) : (opt.name !== undefined ? String(opt.name) : option.value);
                    if (opt.disabled) option.disabled = true;
                } else {
                    option.value = String(opt);
                    option.textContent = String(opt);
                }

                if (isSelected(option.value)) {
                    option.selected = true;
                }
                this.selectElement.appendChild(option);
            });
        } else if (typeof options === 'object' && options !== null) {
            for (const [value, label] of Object.entries(options)) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = String(label);
                if (isSelected(value)) {
                    option.selected = true;
                }
                this.selectElement.appendChild(option);
            }
        }
    }

    /**
     * Fetch dynamic options from backend via GET /api/ui-data
     */
    async loadOptions(overrideParams = null) {
        if (!this.config.action) return;

        try {
            const params = overrideParams || this.config.parameters || {};
            const result = await this.fetchData(this.config.action, params);

            if (result && (Array.isArray(result) || typeof result === 'object')) {
                this.config.options = result;
                this.renderOptions(result);

                if (!this.config.disabled && this.selectElement) {
                    this.selectElement.disabled = false;
                }
            }
        } catch (error) {
            console.error('Failed to load dynamic options for select:', error);
        }
    }

    /**
     * Override setLoading to position the spinner inside the select wrapper
     */
    setLoading(isLoading, options = {}) {
        super.setLoading(isLoading, options);

        if (isLoading && this._uiSpinner && this.wrapperElement) {
            this.wrapperElement.appendChild(this._uiSpinner);
        }
    }

    /**
     * Handle reactive property changes from delta diffs
     */
    update(changes) {
        if (!changes || typeof changes !== 'object') return;

        Object.assign(this.config, changes);

        if (changes.label !== undefined && this.groupElement) {
            let label = this.groupElement.querySelector('label');
            if (changes.label) {
                if (!label) {
                    label = document.createElement('label');
                    this.groupElement.insertBefore(label, this.wrapperElement || this.selectElement);
                }
                label.textContent = changes.label;
            } else if (label) {
                label.remove();
            }
        }

        if (changes.placeholder !== undefined) {
            this.config.placeholder = changes.placeholder;
        }

        if (changes.multiple !== undefined && this.selectElement) {
            this.selectElement.multiple = Boolean(changes.multiple);
        }

        if (changes.options !== undefined) {
            this.renderOptions(changes.options);
        }

        if (changes.value !== undefined && this.selectElement) {
            if (this.config.multiple && Array.isArray(changes.value)) {
                Array.from(this.selectElement.options).forEach(opt => {
                    opt.selected = changes.value.some(v => String(v) === String(opt.value));
                });
            } else {
                this.selectElement.value = changes.value ?? '';
            }
        }

        if (changes.disabled !== undefined && this.selectElement) {
            this.selectElement.disabled = Boolean(changes.disabled);
        }

        if (changes.action !== undefined || changes.parameters !== undefined) {
            if (this.config.action && this.config.auto_fetch !== false) {
                this.loadOptions();
            }
        }
    }

    async handleChange(action, value) {
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
                parameters: { value },
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Select action error:', error);
        }
    }
}

window.UsimSelectComponent = UsimSelectComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('select');
    window.USIM_COMPONENTS.register('select', (id, config) => new UsimSelectComponent(id, config), {
        source: 'modular',
    });
}
