/**
 * Shared content render helpers for modular components.
 */
(function initUsimContentRenderHelpers(global) {
    const namespace = global.USIM_COMPONENT_HELPERS || {};

    const NAMED_ICON_FALLBACKS = {
        home: '🏠',
        menu: '☰',
        search: '🔍',
        settings: '⚙️',
        filter: '🔽',
        refresh: '🔄',
        'external-link': '↗️',
        link: '🔗',
        plus: '➕',
        minus: '➖',
        edit: '✏️',
        trash: '🗑️',
        save: '💾',
        copy: '📋',
        download: '⬇️',
        upload: '⬆️',
        share: '📤',
        print: '🖨️',
        check: '✅',
        close: '✖️',
        info: 'ℹ️',
        warning: '⚠️',
        error: '❌',
        help: '❓',
        bell: '🔔',
        star: '⭐',
        heart: '❤️',
        bolt: '⚡',
        shield: '🛡️',
        user: '👤',
        users: '👥',
        lock: '🔒',
        unlock: '🔓',
        login: '🔑',
        logout: '🚪',
        eye: '👁️',
        'eye-off': '🙈',
        calendar: '📅',
        clock: '🕒',
        file: '📄',
        folder: '📁',
        mail: '✉️',
        sun: '☀️',
        moon: '🌙',
        'chevron-left': '‹',
        'chevron-right': '›',
        'chevron-up': '⌃',
        'chevron-down': '⌄',
        sparkles: '✨',
        ai: '✨',
        bot: '🤖',
        key: '🔑',
        'shield-check': '🛡️',
        'grip-vertical': '⠿',
        drag: '⠿',
        grid: '⊞',
        'layout-grid': '⊞',
        list: '☰',
        sliders: '🎛️',
        'sliders-horizontal': '🎛️',
        columns: '▥',
        maximize: '⛶',
        minimize: '🗕',
        monitor: '🖥️',
        smartphone: '📱',
        pin: '📌',
        bookmark: '🔖',
        tag: '🏷️',
        history: '🕒',
        undo: '↩️',
        redo: '↪️',
        cloud: '☁️',
        'cloud-off': '🌩️',
        'message-square': '💬',
        chat: '💬',
        send: '📤',
        database: '🗄️',
        'credit-card': '💳',
        crown: '👑',
        mic: '🎙️',
    };

    function getCurrentThemeName() {
        const htmlTheme = document.documentElement?.getAttribute('data-theme');
        const bodyTheme = document.body?.getAttribute('data-theme');
        return String(htmlTheme || bodyTheme || 'light').trim().toLowerCase() || 'light';
    }

    function isSvgSource(src) {
        return /^data:image\/svg\+xml/i.test(src) || /\.svg(\?.*)?$/i.test(src);
    }

    function isExplicitUrlSource(iconValue) {
        return (
            iconValue.startsWith('/') ||
            iconValue.startsWith('./') ||
            iconValue.startsWith('../') ||
            iconValue.startsWith('http://') ||
            iconValue.startsWith('https://') ||
            iconValue.startsWith('data:image/')
        );
    }

    function resolveManifestSource(iconValue, themeName) {
        const manifest = global.USIM_ICONS;
        if (!manifest || typeof manifest !== 'object') {
            return null;
        }

        const normalizedKey = iconValue.replace(/^\/+/, '').toLowerCase();
        const extMatch = normalizedKey.match(/\.(svg|png|jpe?g|webp|gif|ico)$/i);

        if (extMatch) {
            const direct = manifest[iconValue] || manifest[normalizedKey];
            if (direct) {
                return { src: direct, name: null };
            }

            const baseKey = normalizedKey.slice(0, -extMatch[0].length);
            const baseEntry = manifest[baseKey];
            if (baseEntry && baseEntry.toLowerCase().endsWith(extMatch[0])) {
                return { src: baseEntry, name: null };
            }

            return null;
        }

        const themedKey = `${normalizedKey}-${themeName}`;
        if (manifest[themedKey]) {
            return { src: manifest[themedKey], name: normalizedKey };
        }

        if (manifest[iconValue]) {
            return { src: manifest[iconValue], name: normalizedKey };
        }

        if (manifest[normalizedKey]) {
            return { src: manifest[normalizedKey], name: normalizedKey };
        }

        return null;
    }

    function resolveIconDescriptor(rawIcon) {
        if (rawIcon === undefined || rawIcon === null) {
            return null;
        }

        const iconValue = String(rawIcon).trim();
        if (!iconValue) {
            return null;
        }

        if (isExplicitUrlSource(iconValue)) {
            return {
                kind: isSvgSource(iconValue) ? 'svg' : 'img',
                src: iconValue,
                name: null,
            };
        }

        const themeName = getCurrentThemeName();
        const manifestHit = resolveManifestSource(iconValue, themeName);
        if (manifestHit) {
            return {
                kind: isSvgSource(manifestHit.src) ? 'svg' : 'img',
                src: manifestHit.src,
                name: manifestHit.name,
            };
        }

        if (/\.(svg|png|jpe?g|webp|gif|ico)(\?.*)?$/i.test(iconValue) || iconValue.includes('/')) {
            return {
                kind: isSvgSource(iconValue) ? 'svg' : 'img',
                src: iconValue,
                name: null,
            };
        }

        const normalizedKey = iconValue.toLowerCase();
        return {
            kind: 'text',
            text: NAMED_ICON_FALLBACKS[normalizedKey] || iconValue,
            name: normalizedKey,
        };
    }

    function createIconElement(rawIcon, options = {}) {
        const descriptor = resolveIconDescriptor(rawIcon);
        if (!descriptor) {
            return null;
        }

        const baseClass = options.baseClass || 'ui-icon';
        const alt = options.alt || '';

        if (descriptor.kind === 'svg') {
            const iconElement = document.createElement('span');
            iconElement.className = `${baseClass} ui-icon-svg`;
            iconElement.style.webkitMaskImage = `url("${descriptor.src}")`;
            iconElement.style.maskImage = `url("${descriptor.src}")`;
            iconElement.setAttribute('aria-hidden', 'true');
            return iconElement;
        }

        if (descriptor.kind === 'img') {
            const iconElement = document.createElement('img');
            iconElement.className = `${baseClass} ui-icon-img`;
            iconElement.src = descriptor.src;
            iconElement.alt = alt;
            if (descriptor.name) {
                iconElement.setAttribute('data-usim-icon-name', descriptor.name);
            }
            return iconElement;
        }

        const iconElement = document.createElement('span');
        iconElement.className = `${baseClass} ui-icon-text`;
        iconElement.textContent = descriptor.text;
        iconElement.setAttribute('aria-hidden', 'true');
        return iconElement;
    }

    function renderButtonContent(button, config = {}, options = {}) {
        button.innerHTML = '';

        const {
            label,
            icon,
            icon_position,
            tooltip,
            aria_label,
        } = config;

        const defaultLabel = options.defaultLabel || 'Button';
        const rawLabel = label !== undefined && label !== null ? String(label) : '';
        const hasLabel = rawLabel.trim() !== '';
        const iconValue = icon !== undefined && icon !== null ? String(icon).trim() : '';
        const isIconOnly = Boolean(iconValue) && !hasLabel;

        button.classList.toggle('ui-button-icon-only', isIconOnly);

        if (!iconValue) {
            button.classList.remove('ui-button-icon-vertical');
            button.textContent = hasLabel ? rawLabel : defaultLabel;
            return;
        }

        const iconElement = createIconElement(iconValue, {
            baseClass: 'ui-button-icon',
            alt: hasLabel ? rawLabel : (aria_label || tooltip || ''),
        });

        if (isIconOnly) {
            button.classList.remove('ui-button-icon-vertical');
            if (!button.getAttribute('aria-label') && (aria_label || tooltip)) {
                button.setAttribute('aria-label', String(aria_label || tooltip));
            }
            if (iconElement) {
                button.appendChild(iconElement);
            }
            return;
        }

        const position = String(icon_position || 'left').toLowerCase();
        const isVertical = position === 'top' || position === 'bottom';
        button.classList.toggle('ui-button-icon-vertical', isVertical);

        const textNode = document.createTextNode(rawLabel);

        if (position === 'right' || position === 'bottom') {
            button.appendChild(textNode);
            if (iconElement) {
                button.appendChild(iconElement);
            }
            return;
        }

        if (iconElement) {
            button.appendChild(iconElement);
        }
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
        } else if (text.includes('\n')) {
            labelElement.style.whiteSpace = 'pre-line';
            labelElement.textContent = text;
        } else {
            labelElement.style.whiteSpace = '';
            labelElement.textContent = text;
        }

        if (config.icon) {
            const iconElement = createIconElement(config.icon, {
                baseClass: 'ui-label-icon',
                alt: text || config.tooltip || config.aria_label || '',
            });

            if (iconElement) {
                const position = String(config.icon_position || 'left').toLowerCase();
                const hasText = text.trim() !== '';

                if (position === 'right') {
                    if (hasText) {
                        iconElement.classList.add('ui-label-icon-right');
                    }
                    labelElement.appendChild(iconElement);
                } else {
                    if (hasText) {
                        iconElement.classList.add('ui-label-icon-left');
                    }
                    labelElement.insertBefore(iconElement, labelElement.firstChild);
                }
            }
        }
    }

    if (!namespace._themeIconListenerAttached) {
        namespace._themeIconListenerAttached = true;
        global.addEventListener('usim:theme-changed', event => {
            const nextTheme = String(event?.detail?.theme || getCurrentThemeName()).toLowerCase();
            document.querySelectorAll('img[data-usim-icon-name]').forEach(img => {
                const iconName = img.getAttribute('data-usim-icon-name');
                if (!iconName) {
                    return;
                }
                const hit = resolveManifestSource(iconName, nextTheme);
                if (hit && hit.src && !isSvgSource(hit.src)) {
                    img.src = hit.src;
                }
            });
        });
    }

    namespace.createIconElement = createIconElement;
    namespace.resolveIconDescriptor = resolveIconDescriptor;
    namespace.renderButtonContent = renderButtonContent;
    namespace.renderLabelContent = renderLabelContent;
    global.USIM_COMPONENT_HELPERS = namespace;
})(window);
