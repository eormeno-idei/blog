/**
 * RichText component (WYSIWYG HTML Editor)
 *
 * Powered by SunEditor v3 (lazy-loaded from vendor/idei/usim/lib/suneditor).
 * Supports toolbar presets ('basic' | 'standard' | 'full' or custom array),
 * image insertion & editing (base64 by default or custom upload URL),
 * character/word counters, automatic USIM light/dark theme synchronization,
 * form value collection via a synced hidden <textarea>, and backend events.
 *
 * Backend contract (JSON):
 *  {
 *    type             : "rich_text",
 *    name             : string,
 *    label            : string|null,
 *    placeholder      : string|null,
 *    value            : string|null,
 *    width            : string|null,
 *    height           : string|null,
 *    min_height       : string|null,
 *    max_height       : string|null,
 *    toolbar          : "basic"|"standard"|"full"|Array,
 *    allow_images     : bool,
 *    image_upload_url : string|null,
 *    max_length       : int|null,
 *    char_counter     : bool,
 *    word_counter     : bool,
 *    statusbar        : bool,
 *    lang             : "es"|"en"|null,
 *    required         : bool,
 *    disabled         : bool,
 *    readonly         : bool,
 *    error            : string|null,
 *    help_text        : string|null,
 *    on_change        : { action, parameters }|null,
 *    on_input         : { action, parameters }|null,
 *    debounce         : int|null,
 *    border_color     : string|null,
 *    border_width     : int|null,
 *    border_radius    : int|null,
 *  }
 */
(function initUsimRichText(global) {
    const TOOLBAR_PRESETS = {
        basic: [
            ['undo', 'redo'],
            '|',
            ['bold', 'italic', 'underline', 'strike'],
            '|',
            ['list_bulleted', 'list_numbered'],
            '|',
            ['link', 'removeFormat'],
        ],
        standard: [
            ['undo', 'redo'],
            '|',
            ['blockStyle', 'fontSize'],
            '|',
            ['bold', 'italic', 'underline', 'strike', 'removeFormat'],
            '|',
            ['fontColor', 'backgroundColor'],
            '|',
            ['align', 'list_bulleted', 'list_numbered', 'outdent', 'indent'],
            '|',
            ['blockquote', 'hr', 'table', 'link', 'image'],
            '|',
            ['fullScreen', 'codeView'],
        ],
        full: [
            ['undo', 'redo'],
            '|',
            ['font', 'fontSize', 'blockStyle'],
            '|',
            ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript', 'removeFormat'],
            '|',
            ['fontColor', 'backgroundColor'],
            '|',
            ['outdent', 'indent', 'align', 'lineHeight', 'list_bulleted', 'list_numbered'],
            '|',
            ['blockquote', 'codeBlock', 'hr', 'table', 'link', 'image', 'video'],
            '|',
            ['fullScreen', 'showBlocks', 'codeView', 'preview', 'print'],
        ],
    };

    const assetPromises = new Map();

    function resolveUsimAssetBase() {
        if (typeof global.USIM_ASSET_BASE === 'string' && global.USIM_ASSET_BASE.trim()) {
            return global.USIM_ASSET_BASE.replace(/\/+$/, '');
        }

        const script = document.querySelector('script[src*="components/rich-text/index.js"]');
        if (script && script.src) {
            const match = script.src.match(/^(.*)\/js\/components\/rich-text\/index\.js/);
            if (match && match[1]) {
                return match[1].replace(/\/+$/, '');
            }
        }

        return '/vendor/idei/usim';
    }

    function ensureStylesheetInjected(id, href) {
        if (document.getElementById(id)) {
            return;
        }
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    function loadStylesheetOnce(id, href) {
        if (document.getElementById(id)) {
            return Promise.resolve();
        }
        if (assetPromises.has(id)) {
            return assetPromises.get(id);
        }

        const promise = new Promise((resolve) => {
            let settled = false;
            const finish = () => {
                if (!settled) {
                    settled = true;
                    resolve();
                }
            };

            const link = document.createElement('link');
            link.id = id;
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = finish;
            link.onerror = finish;
            document.head.appendChild(link);

            // Never stall editor mounting if stylesheet events do not fire (e.g. cached/JSDOM)
            setTimeout(finish, 1500);
        });

        assetPromises.set(id, promise);
        return promise;
    }

    function loadScriptOnce(id, src) {
        if (document.getElementById(id)) {
            return assetPromises.get(id) || Promise.resolve();
        }
        if (assetPromises.has(id)) {
            return assetPromises.get(id);
        }

        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.id = id;
            script.src = src;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = (err) => {
                assetPromises.delete(id);
                script.remove();
                reject(err);
            };
            document.head.appendChild(script);
        });

        assetPromises.set(id, promise);
        return promise;
    }

    async function ensureSunEditorLoaded(langCode) {
        const base = resolveUsimAssetBase();
        const libBase = `${base}/lib/suneditor`;

        const tasks = [];

        if (!global.SUNEDITOR) {
            tasks.push(
                loadStylesheetOnce('usim-suneditor-css', `${libBase}/suneditor.min.css`),
                loadStylesheetOnce('usim-suneditor-dark-css', `${libBase}/suneditor-dark.css`),
                loadScriptOnce('usim-suneditor-js', `${libBase}/suneditor.min.js`)
            );
        } else {
            ensureStylesheetInjected('usim-suneditor-css', `${libBase}/suneditor.min.css`);
            ensureStylesheetInjected('usim-suneditor-dark-css', `${libBase}/suneditor-dark.css`);
        }

        if (langCode === 'es' && !(global.SUNEDITOR_LANG && global.SUNEDITOR_LANG.es)) {
            tasks.push(
                loadScriptOnce('usim-suneditor-lang-es', `${libBase}/lang-es.js`).catch(() => {
                    // Fallback gracefully to SunEditor default (English) if lang file fails
                })
            );
        }

        if (tasks.length > 0) {
            await Promise.all(tasks);
        }
        return global.SUNEDITOR;
    }

    function cloneButtonList(list) {
        return list.map((item) => (Array.isArray(item) ? [...item] : item));
    }

    function filterOutButton(buttonList, buttonName) {
        const cleaned = [];
        for (const item of buttonList) {
            if (Array.isArray(item)) {
                const filtered = item.filter((entry) => entry !== buttonName);
                if (filtered.length > 0) {
                    cleaned.push(filtered);
                }
            } else {
                cleaned.push(item);
            }
        }
        return cleaned;
    }

    class UsimRichTextComponent extends UIComponent {
        constructor(id, config) {
            super(id, config);
            this._textarea = null;
            this._hostElement = null;
            this._bodyElement = null;
            this._bodyContainer = null;
            this._editor = null;
            this._debounceTimer = null;
            this._destroyed = false;
            this._isApplyingExternalUpdate = false;
            this._dirtySinceBlur = false;
            this._lastSyncedValue = this.config.value ?? '';
            this._onThemeChanged = this._handleThemeChanged.bind(this);
        }

        applyCommonAttributes(element) {
            super.applyCommonAttributes(element);
            if (!element) return;

            // Height constraints belong to SunEditor's editable frame, not the outer wrapper
            // (which also contains label, toolbar, and statusbar and can be resized by the user).
            element.style.removeProperty('height');
            element.style.removeProperty('min-height');
            element.style.removeProperty('max-height');
        }

        // ─── Lifecycle ────────────────────────────────────────────────────────

        render() {
            this._destroyed = false;

            const wrapper = document.createElement('div');
            wrapper.className = 'usim-richtext-wrapper';
            if (this.config.error) wrapper.classList.add('has-error');

            this._applyDimensions(wrapper);
            this.applyCommonAttributes(wrapper);

            // Label & help tooltip
            if (this.config.label || this.config.help_text) {
                wrapper.appendChild(this._buildLabelRow());
            }

            // Body container
            const bodyContainer = document.createElement('div');
            bodyContainer.className = 'usim-richtext-body-container';

            const body = document.createElement('div');
            body.className = 'usim-richtext-body';
            this._applyBorderStyles(body);
            bodyContainer.appendChild(body);

            this._bodyElement = body;
            this._bodyContainer = bodyContainer;

            // Hidden synced textarea for form submission & framework value lookups
            const sourceTextarea = document.createElement('textarea');
            sourceTextarea.className = 'usim-richtext-source';
            if (this.config.name) {
                sourceTextarea.name = this.config.name;
                sourceTextarea.id = `usim-rte-${this.id}`;
            }
            sourceTextarea.value = this.config.value ?? '';
            sourceTextarea.disabled = !!this.config.disabled;
            sourceTextarea.readOnly = !!this.config.readonly;
            sourceTextarea.required = !!this.config.required;
            sourceTextarea.setAttribute('aria-hidden', 'true');
            sourceTextarea.tabIndex = -1;
            body.appendChild(sourceTextarea);
            this._textarea = sourceTextarea;

            // Mount host for SunEditor
            const host = document.createElement('div');
            host.className = 'usim-richtext-host';
            body.appendChild(host);
            this._hostElement = host;

            // Subtle placeholder while SunEditor lazy-loads
            const loading = document.createElement('div');
            loading.className = 'usim-richtext-loading';
            loading.textContent = 'Cargando editor…';
            host.appendChild(loading);

            if (this.config.error) {
                const errorIcon = document.createElement('span');
                errorIcon.className = 'ui-input-error-icon usim-richtext-error-icon';
                errorIcon.innerHTML = '⚠️';
                errorIcon.setAttribute('data-tooltip', this.config.error);
                bodyContainer.appendChild(errorIcon);
            }

            wrapper.appendChild(bodyContainer);
            this.element = wrapper;

            global.addEventListener('usim:theme-changed', this._onThemeChanged);
            this._mountSunEditor();

            return wrapper;
        }

        update(newConfig) {
            this.config = { ...this.config, ...newConfig };
            if (!this.element) return;

            if (newConfig.name !== undefined && this._textarea) {
                this._textarea.name = this.config.name || '';
            }

            if (newConfig.value !== undefined) {
                const nextValue = this.config.value ?? '';
                if (this._textarea) {
                    this._textarea.value = nextValue;
                }
                this._lastSyncedValue = nextValue;

                if (this._editor && this._editor.$?.html) {
                    const currentHtml = this._readEditorHtml();
                    if (currentHtml !== nextValue) {
                        this._isApplyingExternalUpdate = true;
                        try {
                            this._editor.$.html.set(nextValue);
                        } finally {
                            this._isApplyingExternalUpdate = false;
                        }
                    }
                }
            }

            if (newConfig.disabled !== undefined) {
                const isDisabled = !!this.config.disabled;
                if (this._textarea) this._textarea.disabled = isDisabled;
                this._syncDisabledState(isDisabled);
            }

            if (newConfig.readonly !== undefined) {
                const isReadonly = !!this.config.readonly;
                if (this._textarea) this._textarea.readOnly = isReadonly;
                this._syncReadonlyState(isReadonly);
            }

            if (newConfig.error !== undefined) {
                this._syncErrorState();
            }

            if (newConfig.help_text !== undefined) {
                this._syncHelpText();
            }

            if (newConfig.border_color !== undefined || newConfig.border_width !== undefined || newConfig.border_radius !== undefined) {
                if (this._bodyElement) this._applyBorderStyles(this._bodyElement);
            }
        }

        destroy() {
            this._destroyed = true;

            if (this._debounceTimer) {
                clearTimeout(this._debounceTimer);
                this._debounceTimer = null;
            }

            global.removeEventListener('usim:theme-changed', this._onThemeChanged);

            if (this._editor) {
                try {
                    this._editor.destroy();
                } catch (err) {
                    console.warn('RichText destroy warning:', err);
                }
                this._editor = null;
            }
        }

        // ─── SunEditor Mounting ───────────────────────────────────────────────

        async _mountSunEditor() {
            const langCode = this._resolveLangCode();

            try {
                const suneditorApi = await ensureSunEditorLoaded(langCode);
                if (this._destroyed || !this._hostElement || !suneditorApi?.create) {
                    return;
                }

                // Clear loading indicator and create a dedicated target element
                this._hostElement.innerHTML = '';
                const editorTarget = document.createElement('div');
                this._hostElement.appendChild(editorTarget);

                const options = this._buildSunEditorOptions(suneditorApi, langCode);
                this._editor = suneditorApi.create(editorTarget, options);

                // Apply initial disabled / readonly states
                if (this.config.disabled) {
                    this._syncDisabledState(true);
                } else if (this.config.readonly) {
                    this._syncReadonlyState(true);
                }

                // Sync current theme once mounted
                this._applyEditorTheme(this._getActiveUsimTheme());
            } catch (error) {
                console.error('RichText failed to load SunEditor:', error);
                if (!this._destroyed && this._hostElement) {
                    this._hostElement.innerHTML = '';
                    // Reveal fallback native textarea if SunEditor fails to load
                    if (this._textarea) {
                        this._textarea.classList.add('is-fallback');
                        this._textarea.removeAttribute('aria-hidden');
                        this._textarea.tabIndex = 0;
                        this._textarea.addEventListener('input', () => this._handleContentChange(this._textarea.value));
                        this._textarea.addEventListener('change', () => this._handleEditorBlur());
                    }
                }
            }
        }

        _buildSunEditorOptions(suneditorApi, langCode) {
            const allowImages = this.config.allow_images !== false;
            const buttonList = this._resolveButtonList(allowImages);
            const isDark = this._getActiveUsimTheme() === 'dark';

            const options = {
                value: this._textarea?.value ?? this.config.value ?? '',
                placeholder: this.config.placeholder ?? '',
                width: '100%',
                height: this.config.height || '280px',
                minHeight: this.config.min_height || '160px',
                buttonList,
                theme: isDark ? 'dark' : '',
                statusbar: this.config.statusbar !== false,
                statusbar_showPathLabel: false,
                statusbar_resizeEnable: true,
                charCounter: Boolean(this.config.char_counter || this.config.max_length != null),
                wordCounter: Boolean(this.config.word_counter),
                events: {
                    onChange: ({ data }) => {
                        const html = typeof data === 'string' ? data : this._readEditorHtml();
                        this._handleContentChange(html);
                    },
                    onBlur: () => {
                        this._handleEditorBlur();
                    },
                },
            };

            if (suneditorApi.plugins) {
                options.plugins = suneditorApi.plugins;
            }

            if (!allowImages) {
                options.excludedPlugins = ['image'];
            } else {
                const imageOptions = {
                    canResize: true,
                    createFileInput: true,
                    createUrlInput: true,
                };
                if (this.config.image_upload_url) {
                    imageOptions.uploadUrl = this.config.image_upload_url;
                }
                options.image = imageOptions;
            }

            if (this.config.max_height) {
                options.maxHeight = this.config.max_height;
            }

            if (this.config.max_length != null && Number.isFinite(Number(this.config.max_length))) {
                options.charCounter_max = Number(this.config.max_length);
            }

            if (langCode === 'es' && global.SUNEDITOR_LANG?.es) {
                options.lang = global.SUNEDITOR_LANG.es;
            }

            return options;
        }

        _resolveButtonList(allowImages) {
            const raw = this.config.toolbar;
            let list;

            if (Array.isArray(raw)) {
                list = cloneButtonList(raw);
            } else {
                const presetName = typeof raw === 'string' && TOOLBAR_PRESETS[raw] ? raw : 'standard';
                list = cloneButtonList(TOOLBAR_PRESETS[presetName]);
            }

            if (!allowImages) {
                list = filterOutButton(list, 'image');
            }

            return list;
        }

        _resolveLangCode() {
            const explicit = typeof this.config.lang === 'string' ? this.config.lang.trim().toLowerCase() : '';
            if (explicit) {
                return explicit.split('-')[0];
            }
            const docLang = (document.documentElement?.getAttribute('lang') || 'es').trim().toLowerCase();
            return docLang.split('-')[0] || 'es';
        }

        _readEditorHtml() {
            if (!this._editor) {
                return this._textarea?.value ?? '';
            }
            try {
                if (typeof this._editor.$?.html?.get === 'function') {
                    return this._editor.$.html.get() ?? '';
                }
                if (typeof this._editor.getContents === 'function') {
                    return this._editor.getContents() ?? '';
                }
            } catch (err) {
                console.warn('RichText read error:', err);
            }
            return this._textarea?.value ?? '';
        }

        // ─── Content & State Sync ─────────────────────────────────────────────

        _handleContentChange(html) {
            const nextValue = typeof html === 'string' ? html : '';
            if (this._textarea) {
                this._textarea.value = nextValue;
            }
            this.config.value = nextValue;

            if (this._isApplyingExternalUpdate) {
                return;
            }

            this._dirtySinceBlur = true;
            this._triggerOnInput(nextValue);
        }

        _handleEditorBlur() {
            const currentValue = this._readEditorHtml();
            if (this._textarea) {
                this._textarea.value = currentValue;
            }
            this.config.value = currentValue;

            if (!this._dirtySinceBlur && currentValue === this._lastSyncedValue) {
                return;
            }

            this._dirtySinceBlur = false;
            this._lastSyncedValue = currentValue;
            this._triggerOnChange(currentValue);
        }

        _syncDisabledState(isDisabled) {
            if (this.element) {
                this.element.classList.toggle('is-disabled', isDisabled);
            }
            if (!this._editor?.$?.ui) return;

            if (isDisabled) {
                this._editor.$.ui.disable?.();
            } else {
                this._editor.$.ui.enable?.();
                if (this.config.readonly) {
                    this._editor.$.ui.readOnly?.(true);
                }
            }
        }

        _syncReadonlyState(isReadonly) {
            if (this.element) {
                this.element.classList.toggle('is-readonly', isReadonly);
            }
            if (!this._editor?.$?.ui) return;

            this._editor.$.ui.readOnly?.(isReadonly);
        }

        // ─── Theme Sync ───────────────────────────────────────────────────────

        _getActiveUsimTheme() {
            const htmlTheme = document.documentElement?.getAttribute('data-theme');
            const bodyTheme = document.body?.getAttribute('data-theme');
            return String(htmlTheme || bodyTheme || 'light').trim().toLowerCase() === 'dark' ? 'dark' : 'light';
        }

        _handleThemeChanged(event) {
            const nextTheme = String(event?.detail?.theme || this._getActiveUsimTheme()).toLowerCase();
            this._applyEditorTheme(nextTheme);
        }

        _applyEditorTheme(themeName) {
            if (!this._editor?.$?.ui?.setTheme) return;
            this._editor.$.ui.setTheme(themeName === 'dark' ? 'dark' : '');
        }

        // ─── UI Helpers (Label, Error, Help, Dimensions, Borders) ─────────────

        _buildLabelRow() {
            const labelRow = document.createElement('div');
            labelRow.className = 'usim-richtext-label-row';

            if (this.config.label) {
                const label = document.createElement('label');
                label.className = 'usim-richtext-label';
                if (this.config.required) label.classList.add('required');
                if (this.config.name) label.setAttribute('for', `usim-rte-${this.id}`);
                label.textContent = this.config.label;
                labelRow.appendChild(label);
            }

            if (this.config.help_text) {
                const helpIcon = document.createElement('span');
                helpIcon.className = 'usim-richtext-help-icon';
                helpIcon.textContent = '?';
                helpIcon.setAttribute('role', 'img');
                helpIcon.setAttribute('aria-label', 'Ayuda');
                helpIcon.setAttribute('data-tooltip', this.config.help_text);
                labelRow.appendChild(helpIcon);
            }

            return labelRow;
        }

        _applyDimensions(el) {
            if (this.config.width) el.style.width = this.config.width;
        }

        _applyBorderStyles(el) {
            if (!el) return;

            const borderColor = this.config.border_color ?? null;
            el.style.borderColor = borderColor || '';
            el.style.borderWidth = this.config.border_width != null ? `${this.config.border_width}px` : '';
            el.style.borderRadius = this.config.border_radius != null ? `${this.config.border_radius}px` : '';
        }

        _syncErrorState() {
            if (!this.element) return;
            this.element.classList.toggle('has-error', !!this.config.error);

            const container = this._bodyContainer || this.element.querySelector('.usim-richtext-body-container') || this.element;
            let errorIcon = container.querySelector('.usim-richtext-error-icon');

            if (this.config.error) {
                if (!errorIcon) {
                    errorIcon = document.createElement('span');
                    errorIcon.className = 'ui-input-error-icon usim-richtext-error-icon';
                    errorIcon.innerHTML = '⚠️';
                    container.appendChild(errorIcon);
                }
                errorIcon.setAttribute('data-tooltip', this.config.error);
            } else if (errorIcon) {
                errorIcon.remove();
            }
        }

        _syncHelpText() {
            if (!this.element) return;
            let labelRow = this.element.querySelector('.usim-richtext-label-row');
            let helpIcon = this.element.querySelector('.usim-richtext-help-icon');

            if (this.config.help_text) {
                if (!helpIcon) {
                    if (!labelRow) {
                        labelRow = document.createElement('div');
                        labelRow.className = 'usim-richtext-label-row';
                        this.element.insertBefore(labelRow, this.element.firstChild);
                    }
                    helpIcon = document.createElement('span');
                    helpIcon.className = 'usim-richtext-help-icon';
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

        // ─── Backend Events ───────────────────────────────────────────────────

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
                const helpers = global.USIM_COMPONENT_HELPERS;
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
                console.error('RichText action error:', error);
            }
        }

        _triggerOnInput(currentValue) {
            if (!this.config.on_input) return;
            const debounce = this.config.debounce ?? 250;
            if (this._debounceTimer) clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => {
                this.triggerAction(
                    this.config.on_input.action,
                    this.buildActionParameters(this.config.on_input.parameters, currentValue),
                    'input'
                );
            }, debounce);
        }

        _triggerOnChange(currentValue) {
            if (!this.config.on_change) return;
            this.triggerAction(
                this.config.on_change.action,
                this.buildActionParameters(this.config.on_change.parameters, currentValue),
                'change'
            );
        }
    }

    global.UsimRichTextComponent = UsimRichTextComponent;
    global.USIM_RICHTEXT_TOOLBAR_PRESETS = TOOLBAR_PRESETS;

    global.USIM_COMPONENTS.register('rich_text', (id, config) => new UsimRichTextComponent(id, config), {
        description: 'RichText — WYSIWYG HTML editor powered by SunEditor v3',
    });
})(window);
