/**
 * Shared content render helpers for modular components.
 */
(function initUsimContentRenderHelpers(global) {
    const namespace = global.USIM_COMPONENT_HELPERS || {};

    function isImageIconSource(iconValue) {
        const hasImageExtension = /\.(svg|png|jpe?g|gif|webp|ico)(\?.*)?$/i.test(iconValue);
        return (
            iconValue.startsWith('/') ||
            iconValue.startsWith('./') ||
            iconValue.startsWith('../') ||
            iconValue.startsWith('http://') ||
            iconValue.startsWith('https://') ||
            iconValue.startsWith('data:image/') ||
            hasImageExtension
        );
    }

    function resolveNamedIcon(iconValue) {
        const namedIcons = {
            settings: '⚙️',
            refresh: '🔄',
            star: '⭐',
            plus: '➕',
            minus: '➖',
            check: '✅',
            close: '✖️',
            warning: '⚠️',
            error: '❌',
            info: 'ℹ️',
        };

        return namedIcons[iconValue.toLowerCase()] || iconValue;
    }

    function applyIconSize(iconElement, iconSize) {
        if (iconSize === undefined || iconSize === null || iconSize === '') {
            return;
        }

        const rawSize = String(iconSize).trim();
        const normalizedSize = (typeof iconSize === 'number' || /^\d+(\.\d+)?$/.test(rawSize))
            ? `${rawSize}px`
            : rawSize;

        iconElement.style.width = normalizedSize;
        iconElement.style.height = normalizedSize;
    }

    function renderButtonContent(button, config = {}, options = {}) {
        button.innerHTML = '';

        const {
            label,
            icon,
            icon_position,
            icon_only,
            icon_color,
            icon_size,
        } = config;

        const defaultLabel = options.defaultLabel || 'Button';
        const forceImageIcon = options.forceImageIcon === true;

        if (!icon) {
            button.textContent = label || defaultLabel;
            return;
        }

        const iconValue = String(icon).trim();
        const isImageSource = forceImageIcon ? true : isImageIconSource(iconValue);
        const resolvedTextIcon = resolveNamedIcon(iconValue);

        const iconElement = document.createElement((icon_color && isImageSource) ? 'span' : (isImageSource ? 'img' : 'span'));
        iconElement.className = 'ui-button-icon';

        applyIconSize(iconElement, icon_size);

        if (isImageSource && icon_color && !forceImageIcon) {
            iconElement.classList.add('ui-button-icon-colored');
            iconElement.style.backgroundColor = icon_color;
            iconElement.style.webkitMaskImage = `url("${iconValue}")`;
            iconElement.style.maskImage = `url("${iconValue}")`;
        } else if (isImageSource) {
            iconElement.src = iconValue;
            iconElement.alt = label || '';
        } else {
            iconElement.classList.add('ui-button-icon-text');
            iconElement.textContent = resolvedTextIcon;
            if (icon_color) {
                iconElement.style.color = icon_color;
            }
        }

        if (icon_only) {
            button.appendChild(iconElement);
            return;
        }

        const position = icon_position || 'left';
        const textNode = document.createTextNode(label || '');

        if (position === 'right' || position === 'bottom') {
            button.appendChild(textNode);
            button.appendChild(iconElement);
            return;
        }

        button.appendChild(iconElement);
        button.appendChild(textNode);
    }

    function renderLabelContent(labelElement, config = {}) {
        const hasHtml = config.html !== undefined && config.html !== null;
        const markdownEnabled = config.markdown === true;

        if (hasHtml) {
            labelElement.innerHTML = String(config.html);
            labelElement.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                [...oldScript.attributes].forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
            return;
        }

        const normalizeText = typeof normalizeTextLineBreaks === 'function'
            ? normalizeTextLineBreaks
            : (value) => String(value || '');

        const text = normalizeText(config.text || '');

        if (markdownEnabled) {
            labelElement.setAttribute('data-markdown', '1');
            if (typeof renderSimpleMarkdownToSafeHtml === 'function') {
                labelElement.innerHTML = renderSimpleMarkdownToSafeHtml(text);
            } else {
                labelElement.textContent = text;
            }
            return;
        }

        if (text.includes('\n')) {
            labelElement.style.whiteSpace = 'pre-line';
            labelElement.textContent = text;
            return;
        }

        labelElement.textContent = text;
    }

    namespace.renderButtonContent = renderButtonContent;
    namespace.renderLabelContent = renderLabelContent;
    global.USIM_COMPONENT_HELPERS = namespace;
})(window);
