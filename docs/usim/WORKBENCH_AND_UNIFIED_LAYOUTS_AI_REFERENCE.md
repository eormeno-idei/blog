# USIM Unified Layouts, Workbench Shell, Split Panes, NavTree & StatusBar — AI Technical Reference

> **Target audience:** AI coding assistants (LLMs) and framework developers.  
> **Scope:** Authoritative contract for USIM's unified layout engine (`AbstractLayout` + `.layout.yaml`), `WorkbenchLayout`, `MainLayout`, `NavTree`, `Split`, `right_panel`, `StatusBar`, `guest_layout`, and standalone/kiosk screens (`$layout = null`).

---

## 1. Unified Layout Architecture

USIM uses **one unified layout mechanism** for both internal Screen containers and full-page application shells:

- Every page layout extends `Idei\Usim\Layout\AbstractLayout` (which extends `Idei\Usim\Screen` and implements `Idei\Usim\Layout\LayoutInterface`).
- A page layout can be defined:
  1. **Declaratively via a sibling `.layout.yaml` file** (recommended; auto-discovered by `<kebab-class-name>.layout.yaml` or `protected static ?string $layoutFile = '...'`, processed by `ContainerLayoutLoader`), OR
  2. **Imperatively via `buildBaseUI(Container $container)` / `startingUI(): Container`** (fully backward compatible, e.g., `MainLayout`).
- `AbstractLayout::callBuildBaseUI()` automatically scans the built tree via `registerSlotsFromTree()` and registers every `Container` carrying `layoutSlot(...)` metadata (including aliases, default `screen`, and `persist_on_navigate`).
- On subsequent requests restored from cache snapshots, `AbstractLayout::postLoadUI()` restores **all** registered slots generically from `$state_slot_map` (`slotName => containerName`).

---

## 2. Layout Resolution Precedence (`Screen::getLayoutClass()`)

Resolution follows a **strict 3-tier precedence**:

| Priority | Declaration on `Screen` | Resolved Layout | Typical Usage |
|---|---|---|---|
| **1 (Highest)** | `public static ?string $layout = null;` | `null` (no layout, no chrome, no status bar, no reset button) | `KioskScreen`, `DevicePairingScreen`, embedded shell screens (`Menu`, `SidebarMenu`, `StatusBar`), standalone modals |
| **2** | `public static ?string $layout = SomeLayout::class;` | `SomeLayout::class` | Screens forcing a specific layout regardless of auth state |
| **3** | `public static ?string $layout = 'default';` *(inherited default)* | If `!Auth::guard('web')->check() && !Auth::guard('device')->check()` and `config('usim.guest_layout')` is non-null → **`guest_layout`**; otherwise → **`default_layout`** | Landing pages (`Home`), auth screens, operational screens (`UsersManager`, `TranslateManager`, `Registered`) |

### Interaction with Role-Based `home_screen`
In `config/usim.php`, each role defines `home_screen` and `priority` (e.g., `root`/`admin` → `UsersManager`, `translator` → `TranslateManager`, `member` → `Home`, `registered` → `Registered`, `smart_tv` → `KioskScreen`).
- A **guest** on `/` (`Home`, `$layout = 'default'`) receives `guest_layout` (e.g., `MainLayout`).
- An authenticated **`member`** whose `home_screen` is `Home::class` automatically receives `default_layout` (`WorkbenchLayout`).
- A paired **`smart_tv`** device landing on `KioskScreen` (`$layout = null`) receives **no layout** regardless of `default_layout`.
- Setting `'guest_layout' => null` in `config/usim.php` makes all `'default'` screens use `default_layout` for both guests and authenticated users.

### `Screen::getLayout()` Guardrail
When the active client screen declares `$layout = null` (or `UIStateManager::hasClientActiveLayout()` is `true` with `getClientActiveLayout() === null`), `Screen::getLayout()` returns `null` and **never** instantiates a phantom `default_layout`.

---

## 3. Configuration Keys (`config/usim.php` & `UsimConfig`)

```php
'default_layout' => 'App\\UI\\Layouts\\WorkbenchLayout', // Authenticated / default shell
'guest_layout' => 'App\\UI\\Layouts\\MainLayout',        // Optional guest shell (null = always use default_layout)
'default_menu_screen' => 'App\\UI\\Screens\\Menu',       // Topbar menu slot default ('top_menu' / 'menu')
'default_sidebar_menu_screen' => 'App\\UI\\Screens\\SidebarMenu', // Workbench 'sidebar' slot default
'default_status_bar_screen' => 'App\\UI\\Screens\\StatusBar',     // Workbench 'status_bar' slot default
```

All keys are exposed via `Idei\Usim\Support\UsimConfig`:
- `$usimConfig->defaultLayout` (`string`)
- `$usimConfig->guestLayout` (`?string`)
- `$usimConfig->defaultMenuScreen` (`string`)
- `$usimConfig->defaultSidebarMenuScreen` (`?string`)
- `$usimConfig->defaultStatusBarScreen` (`?string`)

---

## 4. Declarative `.layout.yaml` Schema for Page Layouts & Nested `Split`

`ContainerLayoutLoader` supports two extensions specifically for unified layouts:

### 4.1 Declarative `Split` Panes (`component: split`)
Supported properties on a `Split` node:
- `orientation`: `'horizontal'` | `'vertical'`
- `split_size`: initial first pane size (e.g., `'260px'`, `'72%'`)
- `splitter_size`: divider thickness (e.g., `'6px'`)
- `min_first_size`, `min_second_size`: minimum pane sizes
- `draggable`: `bool`
- `collapsible`: `bool`
- `collapse_target`: `'first'` | `'second'`
- `collapse_size`: size when collapsed (e.g., `'52px'` for VS Code Activity Bar, `'0px'` for hidden right panel)
- `collapsed_panel`: `'first'` | `'second'` | `null` (initial collapsed state)
- `hide_divider_when_collapsed`: `bool` (hides divider completely when collapsed, e.g. for `0px` right panel)
- `first` / `second`: pane definitions (each may be a `Container` slot, a nested `grid:`, or a nested `component: split`). Each child is automatically tagged with `data-split-pane="first"` or `"second"` for deterministic DOM routing in `split/index.js`.
- **Divider UX (`split/index.js` & `split/index.css`):** Dividers render as subtle `1px` inset hairline channels with centered three-dot handles (`.ui-split-dot`) instead of solid bars and arrow buttons. Handles start hidden, fade in (`0.2s`) on global `window` mouse movement, and fade out (`0.35s`) after 2 seconds of inactivity (remaining visible on hover, drag, or `:focus-visible`). `UsimSplitComponent::destroy()` cleans up the shared listener, timer, and observers.

### 4.2 Declarative Layout Slots
Any `Container` (top-level grid child or `Split` pane) can declare:
- `layout_slot`: `string|true` — canonical slot name registered on `AbstractLayout` (also sets `data-layout-slot="<name>"` in DOM).
- `slot_aliases`: `list<string>` — alternative slot names (e.g., `[content]` for `main`, `[menu]` for `sidebar`, `[right, inspector]` for `right_panel`).
- `screen`: `class-string<Screen>|string` — optional default Screen to auto-embed into this slot.
- `persist_on_navigate`: `bool` (default `true`) — when `false`, navigating the `main` slot automatically calls `clearSlot($slot, collapse: true)`.

### 4.3 Canonical Example (`app/UI/Layouts/workbench-layout.layout.yaml`)
```yaml
name: workbench_layout
appearance: plain
custom_class: usim-workbench-root
width: 100%
height: 100vh
padding: none

grid:
  cols: [1fr]
  rows: [1fr, auto]
  gap: none
  padding: none

  children:
    - split_shell:
        at: [1, 1]
        component: split
        orientation: horizontal
        split_size: 260px
        splitter_size: 6px
        min_first_size: 180px
        min_second_size: 320px
        draggable: true
        collapsible: true
        collapse_target: first
        collapse_size: 52px
        appearance: plain
        width: 100%
        height: 100%
        first:
          name: sidebar_container
          layout_slot: sidebar
          slot_aliases: [menu]
          appearance: plain
        second:
          name: split_workspace
          component: split
          orientation: horizontal
          split_size: 72%
          splitter_size: 6px
          min_first_size: 320px
          min_second_size: 260px
          draggable: true
          collapsible: true
          collapse_target: second
          collapse_size: 0px
          collapsed_panel: second
          hide_divider_when_collapsed: true
          appearance: plain
          width: 100%
          height: 100%
          first:
            name: content_container
            layout_slot: main
            slot_aliases: [content]
            appearance: plain
          second:
            name: right_panel_container
            layout_slot: right_panel
            slot_aliases: [right, inspector]
            persist_on_navigate: false
            appearance: subtle

    - status_bar_container:
        at: [2, 1]
        layout_slot: status_bar
        appearance: plain
        width: 100%
```

And the corresponding PHP class is minimal:
```php
namespace App\UI\Layouts;

use Idei\Usim\Layout\AbstractLayout;

class WorkbenchLayout extends AbstractLayout {}
```

---

## 5. Slot Navigation, Automatic Layout Switching & Right Panel API

### 5.1 Main Navigation & Layout Switching (`HandlesNavigation::showInto`)
`UIStateManager` tracks the layout physically mounted in the browser's `#main` via:
- `UIStateManager::setClientActiveLayout(?string $layoutClass)`
- `UIStateManager::getClientActiveLayout(): ?string`
- `UIStateManager::hasClientActiveLayout(): bool`

When `$this->showInto($targetScreen, slot: 'main', ...)` executes:
- **Same layout (`A → A`):** clears only the target slot (`clear_container: <slot_id>`), embeds `$targetScreen`, emits `navigate`, and auto-clears/collapses any auxiliary slot with `persist_on_navigate: false`.
- **Different layout (`A → B`, `A → null`, or `null → A`):** emits `clear_container: 'main'`, resets the layout singleton, renders `$targetScreen` directly into `parent: 'main'` (which mounts the new layout or standalone screen atomically), updates `setClientActiveLayout()`, and emits `navigate` (`slot: 'main'`, `slot_container_id: 'main'`).

### 5.2 Opening & Closing the Right Panel (`right_panel`)
Any Screen can mount a secondary Screen (inspector, details, preview, AI assistant) in the right panel without blocking the page with a modal:

```php
// Open & auto-expand right_panel (expands owning Split from 0px automatically)
$this->showInto(UserInspector::class, slot: 'right_panel', params: ['user_id' => $id]);

// Close & auto-collapse right_panel back to 0px (from host screen OR from inside UserInspector)
$this->closeSlot('right_panel');
```

On `AbstractLayout` (`LayoutInterface`):
- `expandSlot(string $slot): bool` — finds owning `Split`, expands it, and emits incremental diff `[splitId => ['collapsed_panel' => null]]`.
- `collapseSlot(string $slot): bool` — collapses owning `Split` pane and emits incremental diff.
- `clearSlot(string $slot, bool $collapse = true): bool` — clears slot container, emits `clear_container`, removes active screen, and optionally collapses owning `Split`.

---

## 6. Decoupled Navigation: `MenuBuilder`, `MenuTargetInterface` & `NavTree`

Menu providers (`MainMenu`, `DemosMenuProvider`, `AdminMenuProvider`) implement `MenuProviderInterface` and remain **presentation-agnostic**.

`MenuBuilder` supports two render targets via `Idei\Usim\Navigation\Contracts\MenuTargetInterface`:

| Target Component | Builder Methods | Presentation |
|---|---|---|
| `MenuDropdown` (`type: 'menudropdown'`) | `populate(MenuDropdown)`, `render(?string $id): MenuDropdown` | Dropdown button / floating popover. Supports `->floating(true)` and positions `'bottom-left'`, `'bottom-right'`, `'top-left'`, `'top-right'`, `'right-top'`, `'right-bottom'`. Floating popovers portal to `document.body` with `position: fixed` so they never clip inside `Split` panes (`contain: paint`). |
| `NavTree` (`type: 'navtree'`) | `populateNavTree(NavTree)`, `renderNavTree(?string $id): NavTree` | Vertical sidebar navigation without dropdowns. Submenus render as **inline collapsible accordions** in the client (`->accordion(true)` for single-open mode), with active route/screen highlight (`->activeScreen(...)`) and automatic **Activity Bar** icon-only adaptation when inside `.ui-split-pane.is-collapsed`. |

---

## 7. Workbench Shell Screens

### 7.1 `SidebarMenu` (`app/UI/Screens/SidebarMenu.php`)
- Declares `public static ?string $layout = null;` and uses `App\UI\Screens\Concerns\HandlesShellActions`.
- Structure (`.usim-sidebar-shell`):
  1. Brand header (`.usim-sidebar-brand`) — hides `.usim-sidebar-brand-title` in Activity Bar mode.
  2. Scrollable `NavTree` (`main_menu`) populated from `MainMenu` with `accordion(true)`. Its scroll container (`.usim-sidebar-body`) uses a slim hover-only fade scrollbar (`@property --usim-sidebar-scrollbar-color`) that appears only while the pointer is over `.usim-sidebar-shell`.
  3. VS Code-style footer (`.usim-sidebar-footer`):
     - `user_menu` (`MenuDropdown`, `position('right-top')->floating(true)`): avatar/name trigger, Profile + Logout (auth) or Login + Register (guest).
     - `settings_menu` (`MenuDropdown`, `position('right-top')->floating(true)`): `⚙️` trigger with theme toggle + `🌐` Language submenu (`changeLang`) + About.
- **Activity Bar behavior (`collapse_size: 52px`):** Collapsing `split_shell` applies `.ui-split-pane.is-collapsed`, which automatically switches `SidebarMenu` and `NavTree` into centered icon-only mode. Clicking a submenu group icon in Activity Bar mode automatically expands `split_shell` and opens that accordion group.

### 7.2 `StatusBar` (`app/UI/Screens/StatusBar.php`)
- Declares `public static ?string $layout = null;` and uses `HandlesShellActions`.
- Structure (`.usim-statusbar-shell`, height `28px`):
  - **Left:** `unit_menu` (`position('top-left')->floating(true)`, styled as blue accent badge `.usim-statusbar-unit`). Hidden for guests and users without operational units. Switching units updates `UIStateManager::setActiveUnit()`, Spatie team ID, dispatches `UsimEvent('unit_changed')` (which refreshes `SidebarMenu`'s `NavTree`), and navigates to the role's `home_screen`.
  - **Center:** `lbl_status_message` — updated from any Screen via:
    ```php
    event(new UsimEvent('status_bar_message', ['text' => 'Exporting report...']));
    ```
  - **Right:** **`btn_reset`** (`action: 'reset_app'`), which is **only created when `!app()->isProduction()`**.

---

## 8. Strict Guardrails for AI Agents

1. **NEVER hardcode container IDs** (such as legacy `104921240`) in JS or tests. Always use `UIIdGenerator::generateFromName($layoutClass, $containerName)` in PHP tests and `data-layout-slot` / `slot_container_id` in JS.
2. **NEVER add `#reset-btn` back to `app.blade.php`:** the dev reset control belongs exclusively in `StatusBar` (guarded by `!app()->isProduction()`), keeping standalone/kiosk screens and production builds clean.
3. **NEVER target descendant `.ui-split-pane .ui-container` with `!important` resets in CSS:** only target direct children `.ui-split-pane > .ui-container`, otherwise embedded Screens lose their card borders, padding, gap, and `max-width`.
4. **ALWAYS use `->floating(true)`** (or `right-*` / `top-*` positions) on `MenuDropdown` instances placed inside a `Split` pane or `StatusBar` so the popup portals to `document.body` and escapes `contain: paint`.
5. **NEVER instantiate a layout for `$layout = null` screens** and never force shell screen event notifications in `UsimEventDispatcher` when a standalone screen (`$layout = null`) is active.
6. **ALWAYS sync published assets** after modifying files under `packages/idei/usim/resources/assets/`:
   ```bash
   php artisan vendor:publish --tag=usim-assets --force
   ```
7. **ALWAYS verify** changes with both:
   ```bash
   composer phpstan
   php artisan test
   ```
