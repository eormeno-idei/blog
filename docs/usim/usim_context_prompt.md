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
- `UI::textarea(...)`
- `UI::select(...)`
- `UI::checkbox(...)`
- `UI::table(...)`
- `UI::itemList(...)`
- `UI::image(...)`
- `UI::uploader(...)`
- `UI::menuDropdown(...)`
- `UI::carousel(...)`

### Agnostic Icons (`HasIcon`)
- Components (`Button`, `NewLabel`, `Label`) accept `->icon('check', Align::LEFT)` with semantic names (from the 48-icon library published via `php artisan vendor:publish --tag=usim-icons` or custom files in `public/images/icons/`), `BackedEnum`, emojis (`'🚀'`), or explicit URLs/URIs.
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
- `clear_uploaders`
- `set_uploader_existing_file`
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
