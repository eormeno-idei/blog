# Prompt Template: Generate a USIM Screen Test

Copy and paste this into any agent-capable chat.
Replace values between `<>` before sending.

---

Generate a screen test for a Laravel + Pest + USIM project.

## Context
- Target screen class: `<FQCN_SCREEN>`
- Target test file path: `<TEST_FILE_PATH>`
- Test type: `<initial_load | event_flow | modal | auth | notification | password_reset | other>`
- Requires authentication: `<yes/no>`
- Required role (if any): `<admin/user/none>`
- Must use `uiScenario`: `<yes/no>` (default: yes)

## Expected behavior
1. `<behavior_1>`
2. `<behavior_2>`
3. `<behavior_3>`

## Contracts to validate
- Expected components: `<component_list>`
- Expected actions: `<action_list>`
- Expected response contracts: `<toast|redirect|abort|modal|update_modal|clear_uploaders|other>`
- Extra domain assertions (DB/notification/auth): `<details>`

## Implementation rules
- Follow repository conventions: `uiScenario(...)->component(...)->expect(...)`.
- Avoid raw payload parsing unless strictly necessary.
- Use `Notification::fake()` for notification tests.
- If email links are needed, extract them from `toMail(...)->viewData`.
- End tests with `$ui->assertNoIssues();` when applicable.
- Do not add unrelated code.
- Execute impacted tests and fix failures.

## Output expected
1. Create or update the requested test file with complete implementation.
2. Run relevant test commands.
3. Report:
   - changed files
   - key assertions covered
   - execution status (`passed/failed`)

---

## Quick example

Target screen class: `App\\UI\\Screens\\Auth\\Login`
Target test file path: `tests/Feature/LoginScreenTest.php`
Test type: `event_flow`
Requires authentication: `no`
Expected behavior:
1. loads email/password inputs
2. `submit_login` redirects on valid credentials
3. invalid credentials return error feedback
