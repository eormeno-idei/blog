/**
 * Textarea component
 *
 * Multi-line plain text input with optional character counter,
 * custom borders/backgrounds, and backend input/change events.
 *
 * Backend contract (JSON):
 *  {
 *    type        : "textarea",
 *    name        : string,
 *    label       : string|null,
 *    placeholder : string|null,
 *    value       : string|null,
 *    width       : string|null,   // CSS value e.g. "100%"
 *    height      : string|null,   // CSS value e.g. "200px"
 *    min_length  : int|null,
 *    max_length  : int|null,
 *    required    : bool,
 *    disabled    : bool,
 *    readonly    : bool,
 *    error       : string|null,
 *    help_text   : string|null,
 *    on_change   : { action, parameters }|null,
 *    on_input    : { action, parameters }|null,
 *    debounce    : int|null,
 *  }
 */
class UsimTextareaComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);
        this._debounceTimer = null;
        this._textarea = null;
        this._fieldElement = null;
        this._counter = null;
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };

        if (!this.element) return;

        // Sync textarea value if it changed from backend
        if (this._textarea && newConfig.value !== undefined) {
            this._textarea.value = this.config.value ?? '';
            this._updateCounter();
        }

        if (this._textarea && newConfig.name !== undefined) {
            this._textarea.name = this.config.name || '';
        }

        // Sync error state
        if (newConfig.error !== undefined) {
            this._syncErrorState();
        }

        if (newConfig.help_text !== undefined) {
            this._syncHelpText();
        }

        if (newConfig.disabled !== undefined) {
            if (this._textarea) this._textarea.disabled = !!this.config.disabled;
        }

        if (newConfig.readonly !== undefined) {
            if (this._textarea) this._textarea.readOnly = !!this.config.readonly;
        }

        if (newConfig.min_length !== undefined || newConfig.max_length !== undefined) {
            this._syncLengthConstraints();
            this._updateCounter();
        }

        // Sync border styles if they changed
        if (newConfig.border_color !== undefined || newConfig.border_width !== undefined || newConfig.border_radius !== undefined) {
            if (this._bodyElement) this._applyBorderStyles(this._bodyElement);
        }

        if (newConfig.background_color !== undefined) {
            this._applyBackgroundStyles();
        }
    }

    destroy() {
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }
    }

    render() {
        const wrapper = document.createElement('div');
        wrapper.className = 'usim-textarea-wrapper';
        if (this.config.error) wrapper.classList.add('has-error');

        this._applyDimensions(wrapper);
        this.applyCommonAttributes(wrapper);

        // Label
        if (this.config.label) {
            const labelRow = document.createElement('div');
            labelRow.className = 'usim-textarea-label-row';

            const label = document.createElement('label');
            label.className = 'usim-textarea-label';
            if (this.config.required) label.classList.add('required');
            if (this.config.name) label.setAttribute('for', `usim-ta-${this.id}`);
            label.textContent = this.config.label;
            labelRow.appendChild(label);

            if (this.config.help_text) {
                const helpIcon = document.createElement('span');
                helpIcon.className = 'usim-textarea-help-icon';
                helpIcon.textContent = '?';
                helpIcon.setAttribute('role', 'img');
                helpIcon.setAttribute('aria-label', 'Ayuda');
                helpIcon.setAttribute('data-tooltip', this.config.help_text);
                labelRow.appendChild(helpIcon);
            }

            wrapper.appendChild(labelRow);
        }

        // Body container
        const bodyContainer = document.createElement('div');
        bodyContainer.className = 'usim-textarea-body-container';

        // Body
        const body = document.createElement('div');
        body.className = 'usim-textarea-body mode-plain';
        bodyContainer.appendChild(body);

        // Apply border styles
        this._applyBorderStyles(body);
        this._bodyElement = body;
        this._bodyContainer = bodyContainer;

        body.appendChild(this._createFieldContainer());

        this._applyBackgroundStyles();

        if (this.config.error) {
            const errorIcon = document.createElement('span');
            errorIcon.className = 'ui-input-error-icon usim-textarea-error-icon';
            errorIcon.innerHTML = '⚠️';
            errorIcon.setAttribute('data-tooltip', this.config.error);
            bodyContainer.appendChild(errorIcon);
        }

        wrapper.appendChild(bodyContainer);

        this.element = wrapper;
        return wrapper;
    }

    // ─── Textarea element ─────────────────────────────────────────────────────

    _createFieldContainer() {
        const field = document.createElement('div');
        field.className = 'usim-textarea-field';

        const ta = this._createTextareaEl();
        field.appendChild(ta);
        this._textarea = ta;
        this._fieldElement = field;

        this._syncLengthConstraints();
        this._updateCounter();

        return field;
    }

    _createTextareaEl() {
        const ta = document.createElement('textarea');
        ta.className = 'usim-textarea-el';
        if (this.config.name) {
            ta.name = this.config.name;
            ta.id = `usim-ta-${this.id}`;
        }
        ta.placeholder = this.config.placeholder ?? '';
        ta.value = this.config.value ?? '';
        ta.disabled = !!this.config.disabled;
        ta.readOnly = !!this.config.readonly;
        ta.required = !!this.config.required;
        if (this.config.height) ta.style.height = this.config.height;

        ta.addEventListener('input', () => {
            this._updateCounter();
            this._triggerOnInput();
        });

        ta.addEventListener('change', () => {
            this._triggerOnChange();
        });

        return ta;
    }

    // ─── Dimensions ───────────────────────────────────────────────────────────

    _applyDimensions(el) {
        if (this.config.width) el.style.width = this.config.width;
    }

    // ─── Border Styles ────────────────────────────────────────────────────────

    _applyBorderStyles(el) {
        if (!el) return;

        const borderColor = this.config.border_color ?? null;

        el.style.borderColor = borderColor || '';
        el.style.borderWidth = this.config.border_width != null ? `${this.config.border_width}px` : '';
        el.style.borderRadius = this.config.border_radius != null ? `${this.config.border_radius}px` : '';

        // Apply shadow only if color is set (custom styling)
        if (this.config.border_color) {
            const rgbColor = this._hexToRgb(borderColor);
            if (rgbColor) {
                const [r, g, b] = rgbColor;
                el.style.boxShadow = `0 0 0 2px rgba(${r}, ${g}, ${b}, 0.2), inset 0 0 10px rgba(${r}, ${g}, ${b}, 0.1)`;
            } else {
                el.style.boxShadow = '';
            }
        } else {
            el.style.boxShadow = '';
        }
    }

    _applyBackgroundStyles() {
        const backgroundColor = this.config.background_color;

        if (this._bodyElement) {
            this._bodyElement.style.backgroundColor = backgroundColor || '';
        }

        if (this._textarea) {
            this._textarea.style.backgroundColor = backgroundColor || '';
        }
    }

    _hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? [
            parseInt(result[1], 16),
            parseInt(result[2], 16),
            parseInt(result[3], 16)
        ] : null;
    }

    // ─── Counter ──────────────────────────────────────────────────────────────

    _getLengthConstraints() {
        const rawMin = this.config.min_length;
        const rawMax = this.config.max_length;
        const hasMin = rawMin !== null && rawMin !== undefined && rawMin !== '';
        const hasMax = rawMax !== null && rawMax !== undefined && rawMax !== '';
        const min = hasMin ? Number(rawMin) : null;
        const max = hasMax ? Number(rawMax) : null;

        if (hasMin && hasMax && Number.isFinite(min) && Number.isFinite(max) && min >= max) {
            return { min: null, max: null, invalid: true };
        }

        return {
            min: hasMin && Number.isFinite(min) ? min : null,
            max: hasMax && Number.isFinite(max) ? max : null,
            invalid: false,
        };
    }

    _syncLengthConstraints() {
        if (!this._textarea) return;
        const { min, max } = this._getLengthConstraints();

        if (min !== null && min >= 0) {
            this._textarea.minLength = min;
        } else {
            this._textarea.removeAttribute('minlength');
        }

        if (max !== null && max >= 0) {
            this._textarea.maxLength = max;
        } else {
            this._textarea.removeAttribute('maxlength');
        }
    }

    _updateCounter() {
        if (!this._textarea) return;

        const { min, max, invalid } = this._getLengthConstraints();
        const len = this._textarea.value.length;

        if (!invalid && min === null && max === null) {
            if (this._counter) {
                this._counter.hidden = true;
                this._counter.style.display = 'none';
                this._counter.textContent = '';
                this._counter.classList.remove('is-error', 'is-warning');
                this._counter.removeAttribute('title');
            }
            this._fieldElement?.classList.remove('has-counter');
            return;
        }

        if (!this._counter && this._fieldElement) {
            this._counter = document.createElement('span');
            this._counter.className = 'usim-textarea-counter';
            this._fieldElement.appendChild(this._counter);
        }

        if (!this._counter) return;

        if (invalid) {
            this._counter.hidden = false;
            this._counter.style.display = '';
            this._counter.textContent = '?';
            this._counter.classList.remove('is-error');
            this._counter.classList.add('is-warning');
            this._counter.title = 'min_length debe ser menor que max_length';
            this._fieldElement?.classList.add('has-counter');
            return;
        }

        this._counter.removeAttribute('title');
        this._counter.classList.remove('is-warning');

        if (min !== null && len < min) {
            this._counter.hidden = false;
            this._counter.style.display = '';
            this._counter.textContent = `${len - min}`;
            this._counter.classList.add('is-error');
            this._fieldElement?.classList.add('has-counter');
            return;
        }

        if (max !== null) {
            const remaining = max - len;
            this._counter.hidden = false;
            this._counter.style.display = '';
            this._counter.textContent = `${remaining}`;
            this._counter.classList.toggle('is-error', remaining < 0);
            this._fieldElement?.classList.add('has-counter');
            return;
        }

        this._counter.hidden = true;
        this._counter.style.display = 'none';
        this._counter.textContent = '';
        this._counter.classList.remove('is-error');
        this._fieldElement?.classList.remove('has-counter');
    }

    // ─── Error & Help sync ────────────────────────────────────────────────────

    _syncErrorState() {
        if (!this.element) return;
        this.element.classList.toggle('has-error', !!this.config.error);

        const container = this._bodyContainer || this.element.querySelector('.usim-textarea-body-container') || this.element;
        let errorIcon = container.querySelector('.usim-textarea-error-icon');

        if (this.config.error) {
            if (!errorIcon) {
                errorIcon = document.createElement('span');
                errorIcon.className = 'ui-input-error-icon usim-textarea-error-icon';
                errorIcon.innerHTML = '⚠️';
                container.appendChild(errorIcon);
            }
            errorIcon.setAttribute('data-tooltip', this.config.error);
        } else if (errorIcon) {
            errorIcon.remove();
        }

        const oldErrEl = this.element.querySelector('.usim-textarea-error-msg');
        if (oldErrEl) oldErrEl.remove();
    }

    _syncHelpText() {
        if (!this.element) return;
        let labelRow = this.element.querySelector('.usim-textarea-label-row');
        let helpIcon = this.element.querySelector('.usim-textarea-help-icon');

        if (this.config.help_text) {
            if (!helpIcon) {
                if (!labelRow) {
                    labelRow = document.createElement('div');
                    labelRow.className = 'usim-textarea-label-row';
                    const label = this.element.querySelector('.usim-textarea-label');
                    if (label) {
                        label.parentNode.insertBefore(labelRow, label);
                        labelRow.appendChild(label);
                    } else {
                        this.element.insertBefore(labelRow, this.element.firstChild);
                    }
                }
                helpIcon = document.createElement('span');
                helpIcon.className = 'usim-textarea-help-icon';
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

    // ─── Backend events ───────────────────────────────────────────────────────

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

    async triggerAction(action, parameters, event = 'input') {
        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, result } = await helpers.sendUiEvent({
                componentId,
                event,
                action,
                parameters,
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Textarea action error:', error);
        }
    }

    _triggerOnInput() {
        if (!this.config.on_input) return;
        const debounce = this.config.debounce ?? 0;
        if (this._debounceTimer) clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            this.triggerAction(
                this.config.on_input.action,
                this.buildActionParameters(this.config.on_input.parameters, this._textarea?.value ?? ''),
                'input'
            );
        }, debounce);
    }

    _triggerOnChange() {
        if (!this.config.on_change) return;
        this.triggerAction(
            this.config.on_change.action,
            this.buildActionParameters(this.config.on_change.parameters, this._textarea?.value ?? ''),
            'change'
        );
    }
}

window.USIM_COMPONENTS.register('textarea', (id, config) => new UsimTextareaComponent(id, config), {
    description: 'Textarea — multi-line plain text input',
});
