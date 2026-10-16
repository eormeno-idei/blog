/**
 * Textarea component
 *
 * Modes:
 *  - 'plain'    : standard resizable textarea with optional character counter.
 *  - 'markdown' : split-view editor (raw left, rendered preview right) with
 *                 a formatting toolbar (bold, italic, headings, lists, etc.).
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
 *    max_length  : int|null,
 *    mode        : "plain"|"markdown",
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
        this._preview = null;
        this._counter = null;
        this._activeTab = 'edit'; // 'edit' | 'preview' (mobile)
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };

        if (!this.element) return;

        // Sync textarea value if it changed from backend
        if (this._textarea && newConfig.value !== undefined) {
            this._textarea.value = this.config.value ?? '';
            this._updatePreview();
            this._updateCounter();
        }

        // Sync error state
        if (newConfig.error !== undefined) {
            this._syncErrorState();
        }

        if (newConfig.disabled !== undefined) {
            if (this._textarea) this._textarea.disabled = !!this.config.disabled;
        }

        // Sync border styles if they changed
        if (newConfig.border_color !== undefined || newConfig.border_width !== undefined || newConfig.border_radius !== undefined) {
            if (this._bodyElement) this._applyBorderStyles(this._bodyElement);
        }

        if (newConfig.background_color !== undefined) {
            this._applyBackgroundStyles();
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

        // Body
        const body = document.createElement('div');
        body.className = `usim-textarea-body mode-${this.config.mode || 'plain'}`;
        wrapper.appendChild(body);

        // Apply border styles
        this._applyBorderStyles(body);
        this._bodyElement = body;

        if (this.config.mode === 'markdown') {
            this._buildMarkdownEditor(body);
        } else {
            this._buildPlainEditor(body);
        }

        this._applyBackgroundStyles();

        // Footer: counter + error
        const footer = document.createElement('div');
        footer.className = 'usim-textarea-footer';

        if (this.config.max_length) {
            this._counter = document.createElement('span');
            this._counter.className = 'usim-textarea-counter';
            this._updateCounter();
            footer.appendChild(this._counter);
        }

        if (this.config.error) {
            const errMsg = document.createElement('span');
            errMsg.className = 'usim-textarea-error-msg';
            errMsg.textContent = this.config.error;
            footer.appendChild(errMsg);
        }

        wrapper.appendChild(footer);

        this.element = wrapper;
        return wrapper;
    }

    // ─── Plain editor ─────────────────────────────────────────────────────────

    _buildPlainEditor(container) {
        const ta = this._createTextareaEl(false);
        container.appendChild(ta);
        this._textarea = ta;
    }

    // ─── Markdown editor ──────────────────────────────────────────────────────

    _buildMarkdownEditor(container) {
        // Toolbar
        const toolbar = this._buildToolbar();
        container.appendChild(toolbar);

        // Mobile tab switcher
        const tabs = document.createElement('div');
        tabs.className = 'usim-textarea-tabs';
        const editTab = this._makeTab('✏️ Editar', 'edit');
        const previewTab = this._makeTab('👁 Preview', 'preview');
        tabs.appendChild(editTab);
        tabs.appendChild(previewTab);
        container.appendChild(tabs);

        // Editor pane
        const editorPane = document.createElement('div');
        editorPane.className = 'usim-textarea-editor-pane';

        const editorTitle = document.createElement('div');
        editorTitle.className = 'usim-textarea-pane-title';
        editorTitle.textContent = 'Editor';
        editorPane.appendChild(editorTitle);

        const ta = this._createTextareaEl(true);
        editorPane.appendChild(ta);
        this._textarea = ta;

        // Preview pane
        const previewPane = document.createElement('div');
        previewPane.className = 'usim-textarea-preview-pane';

        const previewTitle = document.createElement('div');
        previewTitle.className = 'usim-textarea-pane-title';
        previewTitle.textContent = 'Vista previa';
        previewPane.appendChild(previewTitle);

        const preview = document.createElement('div');
        preview.className = 'usim-textarea-preview-content usim-md-preview';
        previewPane.appendChild(preview);
        this._preview = preview;

        // Split wrapper
        const split = document.createElement('div');
        split.className = 'usim-textarea-split';
        if (this.config.height) {
            split.style.height = this.config.height;
        }
        split.appendChild(editorPane);
        split.appendChild(previewPane);
        container.appendChild(split);

        this._updatePreview();

        // Tab switching (mobile)
        editTab.addEventListener('click', () => this._switchTab('edit', editTab, previewTab, editorPane, previewPane));
        previewTab.addEventListener('click', () => this._switchTab('preview', editTab, previewTab, editorPane, previewPane));

        this._editTab = editTab;
        this._previewTab = previewTab;
        this._editorPane = editorPane;
        this._previewPane = previewPane;

        // Ensure mobile opens with editor tab only.
        this._switchTab('edit', editTab, previewTab, editorPane, previewPane);
    }

    _makeTab(label, name) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'usim-textarea-tab' + (name === 'edit' ? ' active' : '');
        btn.textContent = label;
        btn.dataset.tab = name;
        return btn;
    }

    _switchTab(tab, editTab, previewTab, editorPane, previewPane) {
        this._activeTab = tab;
        const isMobile = window.matchMedia('(max-width: 600px)').matches;

        if (!isMobile) {
            // Desktop always shows editor + preview simultaneously.
            editTab.classList.remove('active');
            previewTab.classList.remove('active');
            editorPane.classList.remove('tab-hidden');
            previewPane.classList.remove('tab-hidden');
            this._updatePreview();
            return;
        }

        editTab.classList.toggle('active', tab === 'edit');
        previewTab.classList.toggle('active', tab === 'preview');
        editorPane.classList.toggle('tab-hidden', tab !== 'edit');
        previewPane.classList.toggle('tab-hidden', tab !== 'preview');
        if (tab === 'preview') this._updatePreview();
    }

    // ─── Toolbar ──────────────────────────────────────────────────────────────

    _buildToolbar() {
        const bar = document.createElement('div');
        bar.className = 'usim-textarea-toolbar';

        const groups = [
            [
                { label: 'B',   title: 'Negrita',         fn: () => this._wrapSelection('**', '**') },
                { label: 'I',   title: 'Cursiva',         fn: () => this._wrapSelection('*', '*') },
                { label: 'S',   title: 'Tachado',         fn: () => this._wrapSelection('~~', '~~') },
                { label: '`',   title: 'Código inline',   fn: () => this._wrapSelection('`', '`') },
            ],
            [
                { label: 'H1', title: 'Título 1', fn: () => this._prefixLine('# ') },
                { label: 'H2', title: 'Título 2', fn: () => this._prefixLine('## ') },
                { label: 'H3', title: 'Título 3', fn: () => this._prefixLine('### ') },
            ],
            [
                { label: '• Lista',    title: 'Lista de viñetas',  fn: () => this._prefixLine('- ') },
                { label: '1. Lista',   title: 'Lista numerada',    fn: () => this._prefixLine('1. ') },
                { label: '❝ Cita',     title: 'Cita/blockquote',   fn: () => this._prefixLine('> ') },
            ],
            [
                { label: '```',       title: 'Bloque de código',  fn: () => this._wrapBlock('```\n', '\n```') },
                { label: '🔗 Link',    title: 'Enlace',            fn: () => this._insertLink() },
                { label: '— HR',      title: 'Separador',         fn: () => this._insertText('\n\n---\n\n') },
            ],
        ];

        groups.forEach((group, gi) => {
            if (gi > 0) {
                const sep = document.createElement('span');
                sep.className = 'usim-toolbar-sep';
                bar.appendChild(sep);
            }
            group.forEach(({ label, title, fn }) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'usim-toolbar-btn';
                btn.textContent = label;
                btn.title = title;
                btn.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // keep textarea focus
                    fn();
                });
                bar.appendChild(btn);
            });
        });

        return bar;
    }

    // ─── Textarea element ─────────────────────────────────────────────────────

    _createTextareaEl(inMarkdownMode = false) {
        const ta = document.createElement('textarea');
        ta.className = 'usim-textarea-el';
        if (inMarkdownMode) {
            ta.classList.add('usim-textarea-el-markdown');
        }
        if (this.config.name) ta.id = `usim-ta-${this.id}`;
        ta.placeholder = this.config.placeholder ?? '';
        ta.value = this.config.value ?? '';
        ta.disabled = !!this.config.disabled;
        ta.readOnly = !!this.config.readonly;
        ta.required = !!this.config.required;
        if (this.config.max_length) ta.maxLength = this.config.max_length;
        if (!inMarkdownMode && this.config.height) ta.style.height = this.config.height;

        ta.addEventListener('input', () => {
            this._updatePreview();
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

        if (this._previewPane) {
            this._previewPane.style.backgroundColor = backgroundColor || '';
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

    // ─── Preview ──────────────────────────────────────────────────────────────

    _updatePreview() {
        if (!this._preview || !this._textarea) return;
        const raw = this._textarea.value;
        if (typeof window.marked !== 'undefined') {
            this._preview.innerHTML = window.marked.parse(raw, { breaks: true });
        } else {
            // Fallback: plain text (marked not loaded yet)
            this._preview.textContent = raw;
        }
    }

    // ─── Counter ──────────────────────────────────────────────────────────────

    _updateCounter() {
        if (!this._counter || !this._textarea) return;
        const len = this._textarea.value.length;
        const max = this.config.max_length;
        this._counter.textContent = max ? `${len} / ${max}` : `${len}`;
        this._counter.classList.toggle('near-limit', max && len >= max * 0.9);
        this._counter.classList.toggle('at-limit', max && len >= max);
    }

    // ─── Error sync ───────────────────────────────────────────────────────────

    _syncErrorState() {
        if (!this.element) return;
        this.element.classList.toggle('has-error', !!this.config.error);

        // Update or remove error message in footer
        let errEl = this.element.querySelector('.usim-textarea-error-msg');
        if (this.config.error) {
            if (!errEl) {
                errEl = document.createElement('span');
                errEl.className = 'usim-textarea-error-msg';
                const footer = this.element.querySelector('.usim-textarea-footer');
                if (footer) footer.appendChild(errEl);
            }
            errEl.textContent = this.config.error;
        } else if (errEl) {
            errEl.remove();
        }
    }

    // ─── Toolbar helpers ──────────────────────────────────────────────────────

    _wrapSelection(before, after) {
        if (!this._textarea) return;
        const ta = this._textarea;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end) || 'texto';
        const newVal = ta.value.substring(0, start) + before + selected + after + ta.value.substring(end);
        ta.value = newVal;
        ta.selectionStart = start + before.length;
        ta.selectionEnd = start + before.length + selected.length;
        ta.focus();
        this._updatePreview();
        this._updateCounter();
        this._triggerOnInput();
    }

    _prefixLine(prefix) {
        if (!this._textarea) return;
        const ta = this._textarea;
        const start = ta.selectionStart;
        const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = ta.value.indexOf('\n', start);
        const end = lineEnd === -1 ? ta.value.length : lineEnd;
        const line = ta.value.substring(lineStart, end);
        // Toggle: remove prefix if already present
        let newLine, cursorOffset;
        if (line.startsWith(prefix)) {
            newLine = line.substring(prefix.length);
            cursorOffset = -prefix.length;
        } else {
            newLine = prefix + line;
            cursorOffset = prefix.length;
        }
        ta.value = ta.value.substring(0, lineStart) + newLine + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + cursorOffset;
        ta.focus();
        this._updatePreview();
        this._updateCounter();
        this._triggerOnInput();
    }

    _wrapBlock(before, after) {
        if (!this._textarea) return;
        const ta = this._textarea;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end) || 'código';
        const inserted = before + selected + after;
        ta.value = ta.value.substring(0, start) + inserted + ta.value.substring(end);
        ta.selectionStart = start + before.length;
        ta.selectionEnd = start + before.length + selected.length;
        ta.focus();
        this._updatePreview();
        this._updateCounter();
        this._triggerOnInput();
    }

    _insertLink() {
        if (!this._textarea) return;
        const ta = this._textarea;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end) || 'texto del enlace';
        const snippet = `[${selected}](https://ejemplo.com)`;
        ta.value = ta.value.substring(0, start) + snippet + ta.value.substring(end);
        ta.selectionStart = start + selected.length + 3; // point inside URL
        ta.selectionEnd = start + snippet.length - 1;
        ta.focus();
        this._updatePreview();
        this._updateCounter();
        this._triggerOnInput();
    }

    _insertText(text) {
        if (!this._textarea) return;
        const ta = this._textarea;
        const pos = ta.selectionStart;
        ta.value = ta.value.substring(0, pos) + text + ta.value.substring(pos);
        ta.selectionStart = ta.selectionEnd = pos + text.length;
        ta.focus();
        this._updatePreview();
        this._updateCounter();
        this._triggerOnInput();
    }

    // ─── Backend events ───────────────────────────────────────────────────────

    _triggerOnInput() {
        if (!this.config.on_input) return;
        const debounce = this.config.debounce ?? 0;
        if (this._debounceTimer) clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            this.triggerAction(
                this.config.on_input.action,
                { ...this.config.on_input.parameters, value: this._textarea?.value ?? '' }
            );
        }, debounce);
    }

    _triggerOnChange() {
        if (!this.config.on_change) return;
        this.triggerAction(
            this.config.on_change.action,
            { ...this.config.on_change.parameters, value: this._textarea?.value ?? '' }
        );
    }
}

window.USIM_COMPONENTS.register('textarea', (id, config) => new UsimTextareaComponent(id, config), {
    description: 'Textarea — plain or markdown editor',
});
