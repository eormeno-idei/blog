/**
 * Label component (modular override)
 */
class UsimLabelComponent extends UIComponent {
    render() {
        const hasHtml = this.config.html !== undefined && this.config.html !== null;
        const markdownEnabled = this.config.markdown === true;
        const label = document.createElement(hasHtml || markdownEnabled ? 'div' : 'span');

        let classes = hasHtml
            ? 'ui-html-view'
            : `ui-label ${this.config.style || 'default'}`;

        if (!hasHtml && this.config.text_align) {
            classes += ` text-${this.config.text_align}`;
        }

        label.className = classes;

        const helpers = window.USIM_COMPONENT_HELPERS;
        if (helpers?.renderLabelContent) {
            helpers.renderLabelContent(label, this.config);
        } else if (hasHtml) {
            label.innerHTML = String(this.config.html);
        } else {
            label.textContent = normalizeTextLineBreaks(this.config.text || '');
        }

        return this.applyCommonAttributes(label);
    }
}

window.UsimLabelComponent = UsimLabelComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('label');
    window.USIM_COMPONENTS.register('label', (id, config) => new UsimLabelComponent(id, config), {
        source: 'modular',
    });
}
