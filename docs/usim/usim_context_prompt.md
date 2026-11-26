# USIM Context Prompt (Template for AI Chats)

Use this prompt at the beginning of any AI chat so the assistant understands how USIM works, how the team develops with it, and what conventions to follow.

---

You are assisting on a project built with **USIM (UI Services Implementation Model)** for Laravel.
Treat this as a **server-driven UI** framework where the backend defines UI structure, behavior, and state.

## 1) High-Level Framework Model

USIM is backend-driven:

- UI is declared in PHP using builders (`UI::*`).
- Screens are classes (services) that extend `Screen`.
- Client (JS renderer) is generic and applies JSON UI trees + JSON diffs.
- User interactions are sent to `/api/ui-event`.
- Backend restores screen state, executes event handlers, computes diffs, and returns only changes.

Do not assume React/Vue component architecture. The source of truth is the backend service state.

## 2) Core Architecture

### Screens
- Implemented as classes in screen namespaces (commonly `App\\UI\\Screens\\...`).
- Each screen builds UI in `buildBaseUI(Container $container, ...$params)`.
- Optional lifecycle hooks may run after initial build.
- Route-like access maps to screen path conventions.

### UI components
Created with fluent builders, for example:
- `UI::container(...)` (supports `appearance()` / `ContainerAppearance`: `plain`, `subtle`, `card`, `elevated`, `highlight`, `contrast`)
- `UI::newLabel(...)` (preferred over deprecated `UI::label(...)`)
- `UI::button(...)`
- `UI::input(...)`
- `UI::textarea(...)` (plain multi-line text only; do NOT call removed `markdown()`/`plainText()`/`mode()` methods)
- `UI::richText(...)` (WYSIWYG HTML editor powered by lazy-loaded SunEditor v3 with `basicToolbar()`/`standardToolbar()`/`fullToolbar()`, editable base64 or upload images, char/word counters, `rte_` YAML prefix, and automatic light/dark theme sync; see `RICH_TEXT_AI_REFERENCE.md`)
- `UI::select(...)`
- `UI::checkbox(...)`
- `UI::table(...)`
- `UI::itemList(...)`
- `UI::itemCard(...)` (preferred over deprecated `UI::card(...)`; first-class standardized card usable standalone or managed inside `CardGrid`, closed typed slots with no raw HTML, theme-safe `Style` borders, `icrd_` YAML prefix; see `CARD_GRID_AI_REFERENCE.md`)
- `UI::cardGrid(...)` (pure server-driven responsive 2D card grid managing child `ItemCard` components with deterministic IDs `<grid>__card_<itemId>`, wired to `CardListable` or adapted `Listable` over `POST /api/ui-event` so `ComponentDiffer` natively diffs single-card mutations via `$grid->card($id)`, `removeCard()`, `prependCard()`, and `load_more` appends; `grd_` YAML prefix; see `CARD_GRID_AI_REFERENCE.md`)
- `UI::image(...)`
- `UI::uploader(...)`
- `UI::menuDropdown(...)` (supports `->floating(true)` and `right-top`/`top-left`/`top-right` viewport-portaled popovers)
- `UI::navTree(...)` (vertical sidebar navigation with recursive inline collapsible accordions, `getItems()`/`setItems()`, and Activity Bar mode via `compact(true)` + `onGroupClick()` + `activeGroup()`; populated via `MenuBuilder::renderNavTree()`)
- `UI::split(...)` (resizable/collapsible two-pane container; supports declarative `first:`/`second:` panes, `split_size_target` (`first`/`second`), `collapse_size`, and `hide_divider_when_collapsed` in `.layout.yaml`)
- `UI::carousel(...)`

### Unified Layouts & Workbench Shell
- **Built-in Layouts (do NOT recreate `WorkbenchLayout` or `MainLayout` from scratch):** USIM ships `Idei\Usim\Layout\WorkbenchLayout` (with sibling `workbench-layout.layout.yaml`) and `Idei\Usim\Layout\MainLayout` directly in the package, and `usim:install` scaffolds thin app overrides (`App\UI\Layouts\WorkbenchLayout`, `App\UI\Layouts\MainLayout`) plus the Workbench shell screens (`SidebarMenu`, `SidebarSubmenuPanel`, `StatusBar`, `Concerns\HandlesShellActions`) and shared `MainMenu`.
- **Prefer scaffolding new Screens via CLI before editing by hand:**
  - `php artisan usim:screen Orders/OrderExplorer --layout=workbench --slots=top_bar,left_panel,right_panel,status_bar --visibility=authenticated --i18n --test --no-interaction`
  - Passing `--layout=...`, `--slots=...`, `--yaml`, or declarative presets (`--type=workspace|modal|kiosk`) generates both `<Name>.php` and its auto-discovered sibling `<kebab-name>.layout.yaml` (plus `i18n: auto` lang files with `--i18n`, a Pest test with `--test`, and optional `--discover`). Use `--imperative` only when a PHP-only `startingUI()` screen is explicitly required.
- Page layouts (`AbstractLayout`) share the **same declarative `.layout.yaml` engine** (`ContainerLayoutLoader`) as screen containers, with `layout_slot`, `slot_aliases`, `screen`, `persist_on_navigate`, nested `component: split` panes, and parent-class YAML inheritance.
- Layout resolution precedence in `Screen::getLayoutClass()` / `Screen::resolveLayoutClass()`:
  1. `$layout = null` (or `'none'`) -> standalone/kiosk (no layout, no status bar, no reset button). Always wins.
  2. `$layout = 'workbench' | 'main' | CustomLayout::class` -> explicit layout (`'workbench'` / `'main'` prefer `App\UI\Layouts\*` when present and fall back automatically to `Idei\Usim\Layout\WorkbenchLayout` / `MainLayout`).
  3. `$layout = 'default'` -> `config('usim.guest_layout')` when unauthenticated (if set), otherwise `config('usim.default_layout')` (defaults to `WorkbenchLayout` for authenticated users and `MainLayout` for guests).
- `showInto()` automatically handles SPA layout transitions (`MainLayout <-> WorkbenchLayout <-> null`) via `clear_container: 'main'`.
- Auxiliary slots `left_panel`, `right_panel`, `bottom_panel` (all collapsed to `0px` by default in `Split` panes) and `top_bar` (sticky contextual header above `main`, `visible: false` by default) can be driven in two complementary ways:
  1. **Declarative Screen slot projections (`slots:` root section in `.layout.yaml`):** project inline containers (`grid:`, `layout:`/`children:`), `include:` partials, or declarative `screen:` Sub-Screens into `top_bar` (aliases `header`, `toolbar`), `left_panel`, `right_panel`, `bottom_panel` (aliases `bottom`, `panel`, `console`, `terminal`), `status_bar` (`status_bar_center`), `status_bar_left`, `status_bar_right`, or custom layout slots, with `open` (default `true`), `size` (temporary Split override restored on navigate), `fallback` (`ignore` default or `inline`/`inline_top`/`inline_bottom`), and `persist_on_navigate` (default `false`). Inline/`include:` components stay inside the host Screen's PHP class (`protected` properties, `$state_*`, `i18n: auto`, `on*()` handlers). Control at runtime with `$this->openSlot()`, `$this->closeSlot()` (preserves own-portal state unless `clear: true`), `$this->toggleSlot()`, `$this->isSlotOpen()`, and `$this->slotContainer()`. Screens may use root `grid:` OR `slots.main` / `slots.center` (never both).
  2. **Imperative Sub-Screens:** `$this->showInto($screen, slot: 'left_panel' | 'right_panel' | 'bottom_panel' | 'top_bar')` and `$this->closeSlot(...)`. Contextual screens auto-close when navigating `main`, while persistent navigation panels opt in via `public static bool $persistOnNavigate = true;`.
- `SidebarMenu` renders the fixed `52px` VS Code-style Activity Bar (outside `split_shell`) with compact `NavTree` icons + floating footer popovers (`user_menu` + `settings_menu`), delegating submenu groups (such as `Demos`) to `SidebarSubmenuPanel` inside `left_panel`.
- `StatusBar` hosts organizational unit switching (left) + `status_bar_left`, dynamic status messages via `UsimEvent('status_bar_message', ['text' => ...])` + `status_bar_center` (alias `status_bar`, automatically hiding `lbl_status_message` while active), and `status_bar_right` + the dev-only Reset button (right, automatically omitted in production).
- Full AI technical reference: `WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md` in `packages/idei/usim/docs/` (published to `docs/usim/`).

### Agnostic Icons (`HasIcon`)
- Components (`Button`, `NewLabel`, `Label`) accept `->icon('check', Align::LEFT)` with semantic names (from the 80-icon library published via `php artisan vendor:publish --tag=usim-icons` or custom files in `public/images/icons/`), `BackedEnum`, emojis (`'🚀'`), or explicit URLs/URIs.
- **Do NOT use or reintroduce** `iconColor()`, `iconSize()`, or `iconOnly()` (`icon_color`, `icon_size`, `icon_only`): theme/style/container-appearance color, size, and icon-only detection are handled automatically by the client renderer. See `ICONS_GUIDE.md` in `vendor/idei/usim/docs/` (or published `docs/usim/`).

### Event handling
- Components expose actions (`->action('save_item')`).
- Handler naming convention: `on` + PascalCase action name.
  - `save_item` -> `onSaveItem(...)`
- Handlers support automatic typed parameter resolution and hydration:
  - Strongly-typed arguments resolved by attribute name (`int $user_id`, `string $tab = 'main'`, `BackedEnum $status`, `User $user`).
  - Request injection via `UIRequest $request` or `$this->request()`.
  - Legacy signature `array $params` is fully supported for backwards compatibility.
- Automatic query string propagation:
  - Client JS (`ui-renderer.js` and `ui-event.js`) sends `window.location.search` with `/api/ui-event` and `/api/ui-data`.
  - Available in Screen via `$this->query('param', $default)` or `$this->getQueryParams()`, and via `$request->query('param')`.

### State model
- Screen state is server-side.
- Properties prefixed with `store_` are persisted between requests.
- Request lifecycle:
  1. load screen JSON
  2. keep state snapshot
  3. event request
  4. execute handler
  5. return diff JSON + meta contracts

## 3) Main Contracts Returned to Client

In addition to component diffs, backend may return meta keys such as:

- `toast`
- `redirect`
- `abort`
- `change_theme`
- `change_language`
- `modal`
- `update_modal`
- `clear_container` / `clear_containers`
- `clear_uploaders`
- `set_uploader_existing_file`
- `navigate`
- `agent_context`

When implementing features, preserve these contracts and avoid introducing breaking payload shape changes.

## 4) Authentication and Security Patterns

Typical stack used with USIM projects:

- Laravel Sanctum for tokens.
- Spatie Permission for roles/permissions.
- Auth screens like login/profile/recovery may be scaffolded.
- Email verification and password reset may use signed and expiring URLs.

When adding auth features:
- keep validation backend-first,
- keep messages consistent,
- ensure tokens/links can expire,
- avoid exposing sensitive internal state.

## 5) Development Workflow Expectations

When implementing or modifying features:

1. Respect existing screen architecture and naming conventions.
2. Reuse existing services and helpers before adding new abstractions.
3. Keep components and actions explicit and readable.
4. Prefer incremental diffs and minimal side effects.
5. Keep backward compatibility when possible (especially for payload contracts).

## 6) Testing Strategy (Important)

Preferred test style is screen-contract testing with Pest:

- Use `uiScenario(...)` for browser-like event flow.
- Assert component contracts with `component(...)->expect(...)`.
- Trigger actions using `click/action/change/input` helpers.
- Assert response contracts (`toast`, `redirect`, etc.).
- Assert domain side effects (DB changes, notifications, auth status).
- End with `assertNoIssues()` when applicable.

For notification/email tests:
- Use `Notification::fake()`.
- Extract generated links from `toMail(...)->viewData`.
- Avoid dependency on external mail tools (Mailpit/SMTP) in tests.

## 7) Coding Conventions for This Project

- Favor clear, explicit code over clever abstractions.
- Keep comments concise and only where they add real value.
- Avoid changing unrelated files.
- Do not break existing route or payload contracts.
- If behavior differs from assumptions, inspect code before proposing changes.

## 8) Response Style for This Project

When asked to implement something:

- provide concrete code changes,
- validate with tests/commands,
- summarize modified files and outcomes,
- call out trade-offs or compatibility impacts.

If blocked by missing context, ask focused questions with minimal ambiguity.

## 9) Ready-to-Use Task Block (Fill Before Sending)

Task objective:
- `<describe feature or fix>`

Files likely involved:
- `<file_1>`
- `<file_2>`

Expected contracts:
- `<toast/redirect/modal/etc>`

Validation required:
- `<tests to run>`

Constraints:
- `<performance/security/backward compatibility requirements>`

---

Use this context as baseline for all decisions in this chat.
If code and this prompt conflict, trust the current repository code as source of truth and report the mismatch.
