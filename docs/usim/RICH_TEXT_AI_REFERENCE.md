# USIM `RichText` Component (`UI::richText()`) — AI Technical Reference

> **Target audience:** AI coding assistants (LLMs) and framework maintainers.  
> **Scope:** Authoritative backend, JSON contract, frontend runtime, lifecycle, security, and testing specification for `Idei\Usim\Components\RichText` (`type: 'rich_text'`) and the plain-text-only `Idei\Usim\Components\Textarea`.

---

## 1. Component Boundary & Non-Negotiable Rules

1. **Strict separation between `Textarea` and `RichText`:**
   - `UI::textarea()` (`Idei\Usim\Components\Textarea`, `type: 'textarea'`) is **plain text only**.
   - **Do NOT call or generate** `->markdown()`, `->plainText()`, or `->mode('markdown')` on `Textarea` — those methods and `marked.min.js` were **removed in v2.18.0**.
   - Use **`UI::richText()`** (`Idei\Usim\Components\RichText`, `type: 'rich_text'`) whenever a Screen needs WYSIWYG HTML editing, rich formatting, tables, or inline editable images.
2. **Lazy vendor loading:**
   - SunEditor v3.4.3 (`suneditor.min.js`, `suneditor.min.css`, `suneditor-dark.css`, `lang-es.js`) lives in `packages/idei/usim/resources/assets/lib/suneditor/` and is published to `public/vendor/idei/usim/lib/suneditor/`.
   - **Do NOT add `<script src="...suneditor.min.js">` or `<link href="...suneditor.min.css">` to `app.blade.php`.** Assets are injected lazily on first `rich_text` mount using `window.USIM_ASSET_BASE`.
3. **Outer wrapper height guardrail:**
   - `UsimRichTextComponent::applyCommonAttributes()` intentionally strips `height`, `min-height`, and `max-height` from `.usim-richtext-wrapper` and forwards them exclusively to SunEditor's editable frame options (`height`, `minHeight`, `maxHeight`).
   - **Do NOT apply fixed height to `.usim-richtext-wrapper`**, otherwise SunEditor's chrome (label + toolbar + status bar + user resize) overflows and overlaps sibling components.
4. **Security & storage:**
   - `RichText` outputs HTML (potentially containing `data:image/...;base64,...` strings when `image_upload_url` is `null`).
   - When persisting user-submitted HTML that will later be rendered via `UI::newLabel()->html(...)`, **always sanitize server-side** with an allowlist sanitizer (note: `content-render.js` assigns `innerHTML` directly for `html` labels).
   - Recommend `MEDIUMTEXT` / `LONGTEXT` database columns when `allow_images` is enabled in base64 mode.

---

## 2. JSON Contract (`type: "rich_text"`)

```json
{
  "type": "rich_text",
  "name": "article_body",
  "label": "Contenido",
  "placeholder": "Escribe aquí…",
  "value": "<p>Hola <strong>mundo</strong></p>",
  "width": "100%",
  "height": "320px",
  "min_height": "160px",
  "max_height": "600px",
  "toolbar": "standard",
  "allow_images": true,
  "image_upload_url": null,
  "max_length": 2000,
  "char_counter": true,
  "word_counter": true,
  "statusbar": true,
  "lang": "es",
  "required": false,
  "disabled": false,
  "readonly": false,
  "error": null,
  "help_text": "Ayuda contextual",
  "on_change": { "action": "on_article_saved", "parameters": {} },
  "on_input": { "action": "on_article_typing", "parameters": {} },
  "debounce": 250,
  "border_color": null,
  "border_width": null,
  "border_radius": null
}
```

### Toolbar Presets (SunEditor v3 Button/Plugin Names)
Notice SunEditor **v3** uses `blockStyle`, `backgroundColor`, `list_bulleted`, `list_numbered` (NOT v2 names like `formatBlock` or `hiliteColor`):
- `'basic'`: `undo, redo | bold, italic, underline, strike | list_bulleted, list_numbered | link, removeFormat`
- `'standard'` *(default)*: `undo, redo | blockStyle, fontSize | bold, italic, underline, strike, removeFormat | fontColor, backgroundColor | align, list_bulleted, list_numbered, outdent, indent | blockquote, hr, table, link, image | fullScreen, codeView`
- `'full'`: `undo, redo | font, fontSize, blockStyle | bold, underline, italic, strike, subscript, superscript, removeFormat | fontColor, backgroundColor | outdent, indent, align, lineHeight, list_bulleted, list_numbered | blockquote, codeBlock, hr, table, link, image, video | fullScreen, showBlocks, codeView, preview, print`
- Custom array: any valid SunEditor v3 `buttonList` array passed via `->toolbar([...])`.
- When `allow_images === false`: `'image'` is stripped from every button group and `excludedPlugins: ['image']` is passed to `SUNEDITOR.create()`.

---

## 3. Backend Integration Points

| File | Responsibility |
|---|---|
| `packages/idei/usim/src/Components/RichText.php` | Fluent builder (`type = 'rich_text'`). Inherits `HasSizing` (`width`, `height`, `minWidth`, `maxWidth`, `minHeight`, `maxHeight`) and `HasMargin` from `UIComponent`. |
| `packages/idei/usim/src/UI.php` | Factory method `UI::richText(?string $name = null): RichText`. |
| `packages/idei/usim/src/Support/ScreenLifecycleOrchestrator.php` | Includes `'rich_text'` in client-to-server `syncClientConfig('value', ...)` syncing (both trigger component and form parameter sync) and `'rich_text', 'richtext' => RichText::class` in `resolveComponentClassForType()`. |
| `packages/idei/usim/src/Support/ContainerLayoutLoader.php` | Prefix `'rte_' => RichText::class`, aliases `'rich_text'` & `'richtext'`, and `i18n: auto` hydration (`primary: 'label'`, `groups: ['inputs', 'fields']`, `attributes: ['label', 'placeholder', 'help_text', 'tooltip']`). |

---

## 4. Frontend Runtime Architecture

| File | Responsibility |
|---|---|
| `packages/idei/usim/resources/assets/js/components/rich-text/index.js` | `UsimRichTextComponent` registered as `'rich_text'` in `window.USIM_COMPONENTS`. |
| `packages/idei/usim/resources/assets/css/components/rich-text/index.css` | Wrapper styles, label/help/error visuals, fallback textarea style, and USIM light/dark CSS token blending over `.sun-editor`. |
| `packages/idei/usim/resources/assets/js/components/button/index.js` | `collectContextValues()` skips any input/select/checkbox/radio inside `.sun-editor, .sun-editor-common` so SunEditor's internal modal radios (e.g. `suneditor_image_radio`) never leak into USIM event payloads. |
| `packages/idei/usim/resources/views/app.blade.php` | Exposes `window.USIM_ASSET_BASE`, loads `rich-text/index.css` and `rich-text/index.js` (NOT SunEditor vendor files). |

### Key Runtime Mechanics
1. **Form Collection & External Sync:**
   - Renders a hidden sibling `<textarea class="usim-richtext-source" name="...">` BEFORE `.usim-richtext-host`.
   - SunEditor `events.onChange` updates `this._textarea.value` and `this.config.value`, then dispatches debounced `on_input` (default `250ms`).
   - SunEditor `events.onBlur` dispatches `on_change` only if content changed since last sync (`_dirtySinceBlur`).
   - `update({ value })` sets `_isApplyingExternalUpdate = true` around `editor.$.html.set(nextValue)` so backend updates never echo `on_input` back to the server.
2. **State & Theme Sync:**
   - `disabled` → `editor.$.ui.disable()` / `enable()`.
   - `readonly` → `editor.$.ui.readOnly(bool)`.
   - `usim:theme-changed` → `editor.$.ui.setTheme(theme === 'dark' ? 'dark' : '')`.
   - `destroy()` clears timers, unsubscribes from `usim:theme-changed`, sets `_destroyed = true` (guarding against late async loads), and calls `editor.destroy()`.
3. **Graceful Fallback:**
   - If `suneditor.min.js` fails to load, `.usim-richtext-source` gets `.is-fallback` and becomes a usable native `<textarea>` wired to `input`/`change`.

---

## 5. Testing Patterns

- **Backend / Screen tests (Pest):** Use `uiScenario($this, ScreenClass::class)` and post `change` / `input` / form button `click` events to `/api/ui-event` with HTML payloads. Reference: `tests/Unit/RichTextComponentTest.php`.
- **Frontend unit tests (Node + JSDOM):** Pre-define `dom.window.SUNEDITOR` and `dom.window.SUNEDITOR_LANG` mocks before loading `rich-text/index.js` so `ensureSunEditorLoaded()` bypasses network `<script>` loading deterministically. Reference: `tests/js/rich-text-component.test.mjs`.
