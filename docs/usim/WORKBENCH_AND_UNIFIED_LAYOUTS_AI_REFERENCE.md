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
  cols: [52px, 1fr]
  rows: [1fr, auto]
  gap: none
  padding: none

  children:
    - sidebar_container:
        at: [1, 1]
        layout_slot: sidebar
        slot_aliases: [activity_bar, menu]
        appearance: plain
        width: 52px
        height: 100%

    - split_shell:
        at: [1, 2]
        component: split
        orientation: horizontal
        split_size: 250px
        splitter_size: 6px
        min_first_size: 180px
        min_second_size: 320px
        draggable: true
        collapsible: true
        collapse_target: first
        collapse_size: 0px
        collapsed_panel: first
        hide_divider_when_collapsed: true
        appearance: plain
        width: 100%
        height: 100%
        first:
          name: left_panel_container
          layout_slot: left_panel
          slot_aliases: [left, explorer]
          persist_on_navigate: false
          appearance: subtle
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
        span: full
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

### 5.2 Opening & Closing Auxiliary Side Panels (`left_panel` & `right_panel`)
Any Screen can mount a secondary Screen (explorer, navigation tree, inspector, details, preview, AI assistant) in either `left_panel` or `right_panel` without blocking the page with a modal:

```php
// Open & auto-expand left_panel or right_panel (expands owning Split from 0px automatically)
$this->showInto(ProjectExplorer::class, slot: 'left_panel');
$this->showInto(UserInspector::class, slot: 'right_panel', params: ['user_id' => $id]);

// Close & auto-collapse back to 0px (from host screen OR from inside the panel screen)
$this->closeSlot('left_panel');
$this->closeSlot('right_panel');
```

- **Navigation persistence override (`Screen::$persistOnNavigate`):** Both `left_panel` and `right_panel` declare `persist_on_navigate: false` so contextual panels mounted by a Screen automatically clear and collapse to `0px` when navigating away in `main`. Persistent shell navigation panels (such as `SidebarSubmenuPanel`) opt in via `public static bool $persistOnNavigate = true;` so browsing submenu items keeps `left_panel` open while still yielding cleanly whenever a Screen mounts its own contextual panel into `left_panel`.

On `AbstractLayout` (`LayoutInterface`):
- `expandSlot(string $slot): bool` — finds owning `Split`, expands it, and emits incremental diff `[splitId => ['collapsed_panel' => null]]` (also re-emits when `$split->getCollapseTarget() === $side` for client-desync resilience).
- `collapseSlot(string $slot): bool` — collapses owning `Split` pane and emits incremental diff.
- `clearSlot(string $slot, bool $collapse = true): bool` — clears slot container, emits `clear_container`, removes active screen, and optionally collapses owning `Split`.

### 5.3 Declarative Screen Slot Projections (`slots:` in `.layout.yaml`)
A `Screen` can declaratively project its own containers/components (inline, `include:`, or declarative `screen:` Sub-Screens) into slots of the active `AbstractLayout` (`left_panel`, `right_panel`, `status_bar`/`status_bar_center`, `status_bar_left`, `status_bar_right`, or custom layout slots) via a root `slots:` section:

- **Main slot symmetry:** Screens may use root `grid:` OR `slots.main` / `slots.center` (declaring both throws `InvalidArgumentException`).
- **Logical tree vs. DOM portal (`portal_slot` + `portal_target_id`):** Projected portal containers stay inside the host `Screen`'s logical container tree & snapshot cache (preserving `protected` property binding, `i18n: auto`, client hydration, diffs, and direct `UIEventController` routing to the host Screen), while `ui-renderer.js` mounts them into `[data-layout-slot="<slot>"]` / `portal_target_id`.
- **Slot controls:** `open` (default `true`), `size` (temporary Split override supporting both `first` and `second` panes via `Split::splitSizeTarget('first'|'second')`, automatically restored on navigation), `fallback` (`ignore` default → `portal_unmounted: true`, or `inline` / `inline_top` / `inline_bottom`), and `persist_on_navigate` (default `false`).
- **Runtime PHP helpers on `Screen`:** `openSlot(string $slot, ?string $size = null)`, `closeSlot(string $slot, bool $collapse = true, ?bool $clear = null)` (preserves own-portal state on collapse unless `clear: true`), `toggleSlot(string $slot)`, `isSlotOpen(string $slot)`, and `slotContainer(string $slot)`.
- **Coexistence with `SidebarSubmenuPanel`:** When a Screen projects `left_panel` (`open: true`), it takes ownership; clicking the Activity Bar switches back to `SidebarSubmenuPanel`; calling `$this->openSlot('left_panel')` re-mounts the Screen's portal cleanly.
- **Multi-slot clearing & atomic diffs:** `UIChangesCollector::add()` accumulates multiple `clear_container` targets in `clear_containers` (handled by `ui-renderer.js`) while preserving `clear_container`, and `UIChangesCollector::patchComponent()` merges incremental Split/StatusBar patches without clobbering initial full-render payloads.

---

## 6. Decoupled Navigation: `MenuBuilder`, `MenuTargetInterface` & `NavTree`

Menu providers (`MainMenu`, `DemosMenuProvider`, `AdminMenuProvider`) implement `MenuProviderInterface` and remain **presentation-agnostic**.

`MenuBuilder` supports two render targets via `Idei\Usim\Navigation\Contracts\MenuTargetInterface`:

| Target Component | Builder Methods | Presentation |
|---|---|---|
| `MenuDropdown` (`type: 'menudropdown'`) | `populate(MenuDropdown)`, `render(?string $id): MenuDropdown` | Dropdown button / floating popover. Supports `->floating(true)` and positions `'bottom-left'`, `'bottom-right'`, `'top-left'`, `'top-right'`, `'right-top'`, `'right-bottom'`. Floating popovers portal to `document.body` with `position: fixed` so they never clip inside `Split` panes (`contain: paint`). |
| `NavTree` (`type: 'navtree'`) | `populateNavTree(NavTree)`, `renderNavTree(?string $id): NavTree` | Vertical sidebar navigation without dropdowns. Submenus render recursively to arbitrary depth as **inline collapsible accordions** (`->accordion(true)`), with active route/screen highlight (`->activeScreen(...)`), `getItems()` / `setItems()`, and **Activity Bar** mode (`->compact(true)`, `->onGroupClick('open_menu_group')`, `->activeGroup(...)`) that delegates top-level group clicks to the backend to open them inside `left_panel`. |

---

## 7. Workbench Shell Screens

### 7.1 `SidebarMenu` (`app/UI/Screens/SidebarMenu.php`) & `SidebarSubmenuPanel` (`app/UI/Screens/SidebarSubmenuPanel.php`)
- Both declare `public static ?string $layout = null;` and use `App\UI\Screens\Concerns\HandlesShellActions`.
- **`SidebarMenu` (Fixed `52px` Activity Bar, `.usim-sidebar-shell.usim-activity-bar`):**
  1. Brand header (`.usim-sidebar-brand`) — centered `⚡` icon with app-name tooltip.
  2. Scrollable `NavTree` (`main_menu`) populated from `MainMenu` with `compact(true)` and `onGroupClick('open_menu_group')`. Its scroll container (`.usim-sidebar-body`) uses a slim hover-only fade scrollbar (`@property --usim-sidebar-scrollbar-color`).
  3. VS Code-style footer (`.usim-sidebar-footer`):
     - `user_menu` (`MenuDropdown`, `position('right-top')->floating(true)`): centered avatar/icon trigger with tooltip, Profile + Logout (auth) or Login + Register (guest).
     - `settings_menu` (`MenuDropdown`, `position('right-top')->floating(true)`): centered `⚙️` trigger with theme toggle + `🌐` Language submenu (`changeLang`) + About.
  - Clicking a top-level submenu group (e.g. `🎮 Demos`) invokes `onOpenMenuGroup()`, opening or toggling `SidebarSubmenuPanel` inside `left_panel` (and recovering `left_panel` if another screen had temporarily claimed it).
- **`SidebarSubmenuPanel` (Primary Side Bar inside `left_panel`, `.usim-left-panel-shell`):**
  - Declares `public static bool $persistOnNavigate = true;` so clicking items inside a submenu keeps the panel open across demo/screen switches.
  - Renders an Explorer-style header (uppercase group title + `✕` close button emitting `UsimEvent('left_panel_closed')`) and a full-width scrollable `NavTree` (`submenu_tree`) with `accordion(true)` and hover-fade scrollbar, supporting unlimited nested sub-sub-menu depth.

### 7.2 `StatusBar` (`app/UI/Screens/StatusBar.php`)
- Declares `public static ?string $layout = null;` and uses `HandlesShellActions`.
- Structure (`.usim-statusbar-shell`, height `28px`):
  - **Left:** `unit_menu` (`position('top-left')->floating(true)`, styled as blue accent badge `.usim-statusbar-unit`), followed by contextual projection slot **`status_bar_left`** (`status_bar_left_slot`, `persist_on_navigate: false`). Hidden for guests and users without operational units. Switching units updates `UIStateManager::setActiveUnit()`, Spatie team ID, dispatches `UsimEvent('unit_changed')` (which refreshes `SidebarMenu`'s `NavTree`), and navigates to the role's `home_screen`.
  - **Center:** `lbl_status_message` (updated from any Screen via `UsimEvent('status_bar_message', ['text' => ...])`) and contextual projection slot **`status_bar_center`** (`status_bar_center_slot`, aliases `status_bar` / `status_bar_items`, `persist_on_navigate: false`). While a Screen projects components into `status_bar` / `status_bar_center`, `lbl_status_message` is automatically hidden (`visible: false`) and restored when cleared or upon navigation.
  - **Right:** contextual projection slot **`status_bar_right`** (`status_bar_right_slot`, `persist_on_navigate: false`), followed by **`btn_reset`** (`action: 'reset_app'`), which is **only created when `!app()->isProduction()`** and always remains anchored on the far right.

---

## 8. Strict Guardrails for AI Agents

1. **NEVER hardcode container IDs** (such as legacy `104921240`) in JS or tests. Always use `UIIdGenerator::generateFromName($layoutClass, $containerName)` in PHP tests and `data-layout-slot` / `slot_container_id` in JS.
2. **NEVER add `#reset-btn` back to `app.blade.php`:** the dev reset control belongs exclusively in `StatusBar` (guarded by `!app()->isProduction()`), keeping standalone/kiosk screens and production builds clean.
3. **NEVER target descendant `.ui-split-pane .ui-container` with `!important` resets in CSS:** only target direct children `.ui-split-pane > .ui-container`, otherwise embedded Screens lose their card borders, padding, gap, and `max-width`.
4. **ALWAYS use `->floating(true)`** (or `right-*` / `top-*` positions) on `MenuDropdown` instances placed inside a `Split` pane or `StatusBar` so the popup portals to `document.body` and escapes `contain: paint`.
5. **NEVER instantiate a layout for `$layout = null` screens** and never force shell screen event notifications in `UsimEventDispatcher` when a standalone screen (`$layout = null`) is active.
6. **NEVER clear `status_bar_container` directly from a Screen:** always project into `status_bar` (`status_bar_center`), `status_bar_left`, or `status_bar_right` so `StatusBar`'s permanent `unit_menu` and dev `btn_reset` remain intact and `lbl_status_message` hides/restores automatically.
7. **ALWAYS use `UIChangesCollector::patchComponent()`** when emitting incremental property deltas on components that may already have a full payload in the same request (e.g. `Split` collapse/size diffs or `lbl_status_message` visibility during initial render).
8. **ALWAYS gate Workbench-panel-dependent menu items** (e.g. `RightPanelDemo`, `SlotProjectionDemo`) with `when: Auth::check()` in `DemosMenuProvider` when `guest_layout` uses `MainLayout`.
9. **ALWAYS sync published assets** after modifying files under `packages/idei/usim/resources/assets/`:
   ```bash
   php artisan vendor:publish --tag=usim-assets --force
   ```
10. **ALWAYS verify** changes with both:
   ```bash
   composer phpstan
   php artisan test
   ```
