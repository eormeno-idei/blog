/**
 * Label component (modular override)
 * Supports both 'label' and 'newlabel' component types.
 */
class UsimLabelComponent extends UIComponent {
    render() {
        const hasHtml = this.config.html !== undefined && this.config.html !== null;
        const markdownEnabled = this.config.markdown === true;
        const tagName = hasHtml || markdownEnabled
            ? 'div'
            : (this.config.for ? 'label' : 'span');

        const label = document.createElement(tagName);
        this.element = label;

        this._applyPresentation(label);
        this._applyContent(label);

        return this.applyCommonAttributes(label);
    }

    update(changes = {}) {
        if (!changes || typeof changes !== 'object') {
            return;
        }

        Object.assign(this.config, changes);

        const target = this.element || document.querySelector(`[data-component-id="${this.id}"]`);
        if (!target) {
            return;
        }

        this.element = target;
        this._applyPresentation(target);

        if (
            changes.text !== undefined ||
            changes.html !== undefined ||
            changes.markdown !== undefined ||
            changes.icon !== undefined ||
            changes.icon_position !== undefined
        ) {
            this._applyContent(target);
        }
    }

    _applyPresentation(label) {
        const hasHtml = this.config.html !== undefined && this.config.html !== null;

        if (hasHtml) {
            label.className = 'ui-html-view';
            return;
        }

        const style = this.config.style || 'default';
        const variant = this.config.variant || 'text';
        const size = this.config.size || 'medium';
        const fontWeight = this.config.font_weight || 'normal';
        const textTransform = this.config.text_transform || 'none';
        const textAlign = this.config.text_align || 'left';
        const animation = this.config.animation || 'none';

        let classes = `ui-label ${style} variant-${variant} size-${size} weight-${fontWeight}`;

        if (textAlign) {
            classes += ` text-${textAlign}`;
        }

        if (textTransform && textTransform !== 'none') {
            classes += ` transform-${textTransform}`;
        }

        if (this.config.truncate === true) {
            classes += ' is-truncated';
        }

        if (this.config.shadow === true) {
            classes += ' has-shadow';
        }

        if (this.config.glow === true) {
            classes += ' has-glow';
        }

        if (this.config.inline === false) {
            classes += ' is-block';
        }

        if (animation && animation !== 'none') {
            classes += ` anim-${animation}`;
        }

        label.className = classes;

        if (this.config.for) {
            label.setAttribute('for', String(this.config.for));
        } else {
            label.removeAttribute('for');
        }

        if (this.config.tooltip) {
            label.title = String(this.config.tooltip);
        } else {
            label.removeAttribute('title');
        }

        if (this.config.max_lines && Number(this.config.max_lines) > 0) {
            label.classList.add('is-clamped');
            label.style.webkitLineClamp = String(Number(this.config.max_lines));
        } else {
            label.style.webkitLineClamp = '';
        }
    }

    _applyContent(label) {
        const hasHtml = this.config.html !== undefined && this.config.html !== null;
        const helpers = window.USIM_COMPONENT_HELPERS;

        if (helpers?.renderLabelContent) {
            helpers.renderLabelContent(label, this.config);
        } else if (hasHtml) {
            label.innerHTML = String(this.config.html);
        } else {
            label.textContent = normalizeTextLineBreaks(this.config.text || '');
        }

        if (!hasHtml && this.config.icon) {
            const iconSpan = document.createElement('span');
            iconSpan.className = 'ui-label-icon';
            iconSpan.textContent = String(this.config.icon);

            if ((this.config.icon_position || 'left') === 'right') {
                label.appendChild(iconSpan);
            } else {
                label.insertBefore(iconSpan, label.firstChild);
            }
        }
    }
}

window.UsimLabelComponent = UsimLabelComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('label');
    window.USIM_COMPONENTS.register('label', (id, config) => new UsimLabelComponent(id, config), {
        source: 'modular',
    });

    window.USIM_COMPONENTS.unregister('newlabel');
    window.USIM_COMPONENTS.register('newlabel', (id, config) => new UsimLabelComponent(id, config), {
        source: 'modular',
    });
}
