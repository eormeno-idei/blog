# Prompt Template: Generate a New USIM Component (End-to-End)

Copy and paste this prompt into an agent-capable chat.
Replace values between `<>` before sending.

---

You are implementing a **new UI component** for a Laravel + USIM project.
USIM is server-driven UI: backend (PHP) defines the component contract, frontend renderer applies JSON and deltas.

## Task objective
- Build a new component named: `<COMPONENT_NAME>`
- Component type key (`type` in JSON): `<component_type_snake_case>`
- Main user goal: `<what user should achieve with this component>`

## Functional requirements
1. `<requirement_1>`
2. `<requirement_2>`
3. `<requirement_3>`
4. `<manual/auto behavior if needed>`
5. `<loop/fullscreen/timeout/other advanced behavior if needed>`

## Contract requirements (JSON)
Define and implement the JSON contract for this component:
- Required props: `<required_props>`
- Optional props: `<optional_props>`
- Event actions: `<action_names>`
- Event payload params: `<params_structure>`
- Delta behavior (update fields only): `<delta_rules>`

Important:
- Preserve reserved meta keys and contracts: `storage`, `action`, `redirect`, `toast`, `abort`, `change_theme`, `change_language`, `modal`, `update_modal`, `clear_uploaders`, `set_uploader_existing_file`, `agent_context`.
- Do not break existing payload shapes.

## Implementation scope (must be complete)
Implement all required pieces end-to-end:

1. **Package backend (PHP)**
- Create builder class in:
  - `packages/idei/usim/src/Services/Components/<ComponentName>Builder.php`
- Register factory method in:
  - `packages/idei/usim/src/Services/UI.php` as `UI::<factoryMethod>()`
- Register backend deserialization mapping in:
  - `packages/idei/usim/src/Services/Screen.php` inside `mapTypeToClass()`

2. **Package frontend (JS/CSS)**
- Create JS component in:
  - `packages/idei/usim/resources/assets/js/<component-file>.js`
- Create CSS (if needed) in:
  - `packages/idei/usim/resources/assets/css/<component-file>.css`
- Register component in renderer factory:
  - `packages/idei/usim/resources/assets/js/ui-renderer.js`
- Load assets in:
  - `packages/idei/usim/resources/views/app.blade.php`

3. **Demo in application**
- Create demo screen:
  - `app/UI/Screens/Demo/<ComponentName>Demo.php`
- Add menu entry in:
  - `app/UI/Screens/Menu.php`

4. **Tests (Pest + uiScenario)**
- Create/extend feature tests in:
  - `tests/Feature/<ComponentName>DemoTest.php`
- Cover:
  - initial contract
  - main interactions/events
  - dynamic updates/deltas
  - edge cases from requirements
- Use `uiScenario(...)` and finish with `$ui->assertNoIssues();` when applicable.

## Mandatory execution commands
Run these commands during implementation:

1. `composer dump-autoload`
2. `php artisan usim:discover`
3. `php artisan test tests/Feature/<ComponentName>DemoTest.php`
4. `php artisan vendor:publish --tag=usim-assets --force`

If relevant, run related menu/contracts tests as regression check.

## Quality checklist (must satisfy all)
- [ ] Builder has clear fluent API and sane defaults.
- [ ] `type` mapping works both on initial load and state reconstruction.
- [ ] Frontend renderer creates the component correctly.
- [ ] `update(newConfig)` applies deltas safely.
- [ ] Events send correct `component_id`, `event`, `action`, `parameters`.
- [ ] No regressions in reserved meta-keys handling.
- [ ] Demo screen proves real usage patterns.
- [ ] Tests pass.
- [ ] Assets are published and usable in current app.

## Constraints
- Keep changes focused; do not edit unrelated files.
- Preserve backward compatibility.
- Reuse existing architecture patterns from current USIM codebase.
- Prefer explicit, readable code over abstractions.

## Output format required from the agent
Provide:
1. Files changed/created.
2. Summary of contract and event flow implemented.
3. Commands executed and results.
4. Any trade-offs, assumptions, or follow-up recommendations.

---

## Quick fill example
- `<COMPONENT_NAME>`: `Carousel`
- `<component_type_snake_case>`: `carousel`
- `<factoryMethod>`: `carousel`
- `<component-file>`: `carousel-component`

Use this template for any new USIM component (charts, timeline, gallery, player, etc.) while keeping package-first integration complete.
