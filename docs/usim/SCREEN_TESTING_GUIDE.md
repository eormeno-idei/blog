# Screen Testing Guide (USIM + Pest)

This guide explains how to write human-friendly, stable screen tests for USIM projects.
It is designed for developers first, with practical patterns and copyable examples.

## 1) Core idea

USIM screen tests validate the JSON UI contract instead of HTML rendering.
In practice, tests should verify:

- component structure
- action wiring
- UI state changes (diffs)
- meta contracts (`toast`, `redirect`, `abort`, `modal`, etc.)

The main helper is `uiScenario(...)`, which simulates frontend behavior over `/api/ui` and `/api/ui-event`.

## 2) Recommended test shape

```php
it('does something in a screen', function () {
    $ui = uiScenario($this, SomeScreen::class, ['reset' => true]);

    $ui->component('some_component')->expect('type')->toBe('button');

    $response = $ui->click('some_component', [
        'some_param' => 'value',
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $ui->assertNoIssues();
});
```

## 3) Available helpers and functions

The exact files may vary in host projects, but these are the standard helpers used by USIM integrations.

### 3.1 `uiScenario(...)` flow helper

Typical signature:

```php
uiScenario(TestCase $test, string $screenClass, array $query = []): UiScenario
```

What it provides:

- screen bootstrapping via `/api/ui/{screen}`
- automatic `X-UI-Client-Id` header per scenario instance (simulating isolated browser tabs)
- in-memory UI snapshot updates and `X-USIM-Storage` forwarding after events
- convenience methods to interact with components by `name`

Common methods on `UiScenario`:

- `component(string $name): UiComponentRef`
- `componentData(string $name): array`
- `click(string $componentName, array $parameters = []): TestResponse`
- `action(string $componentName, string $action, array $parameters = [], bool $includeStorageHeader = true): TestResponse`
- `change(string $componentName, string $action, array $parameters = [], bool $includeStorageHeader = true): TestResponse`
- `input(string $componentName, string $action, array $parameters = []): TestResponse`
- `timeout(int $callerServiceId, string $action, array $parameters = []): TestResponse`
- `issues(): array`
- `assertNoIssues(): self`

Common methods on `UiComponentRef`:

- `expect(string $field)`
- `data(): array`
- `click(array $parameters = []): self`

### 3.2 Raw payload helpers (optional)

These helpers are useful for contract-heavy assertions (modals, nested arrays, action discovery):

- `uiFind(...)`
- `uiPayloadContainsAction(...)`
- `uiPayloadContainsText(...)`
- `uiPayloadContainsStyle(...)`
- `hasModalComponents(...)`
- `modalPayloadHasNamedComponent(...)`
- `firstTimeoutModalComponent(...)`

Use these only when component-level assertions are not enough.

### 3.3 Optional auth helper in tests

Many projects add a helper like:

- `loginAs('admin'|'user')`

Use it when a screen requires authentication/roles to keep tests short and consistent.

## 4) Conventions for maintainable tests

- Prefer `uiScenario + component(...)` over manual payload parsing.
- Keep test names behavior-focused.
- Assert response contracts after events (`assertOk`, `toast`, `redirect`, etc.).
- Assert final state (UI + DB/notifications/auth) when relevant.
- End screen tests with `$ui->assertNoIssues();`.
- Use `Notification::fake()` for email tests (do not depend on Mailpit or SMTP).

## 5) Practical examples

### Initial screen load

```php
$ui = uiScenario($this, Login::class, ['reset' => true]);
$ui->component('login_email')->expect('type')->toBe('input');
$ui->component('btn_submit_login')->expect('action')->toBe('submit_login');
$ui->assertNoIssues();
```

### Click flow + response contract

```php
$response = $ui->click('btn_submit', [
    'name' => 'Alice',
]);

$response->assertOk();
expect($response->json('toast.type'))->toBe('success');
```

### Notification + URL extraction from email

```php
Notification::fake();

// ... trigger action that sends email ...

Notification::assertSentTo($user, SomeNotification::class, function ($notification) use ($user, &$url) {
    $mail = $notification->toMail($user);
    $url = $mail->viewData['someUrl'] ?? null;
    return is_string($url) && $url !== '';
});
```

## 6) Definition of done checklist

- Test name clearly describes expected behavior.
- At least one meaningful behavior is validated.
- Event response contract is asserted.
- Final state is asserted (UI/domain) when applicable.
- `$ui->assertNoIssues();` used when applicable.
- File-level tests were executed locally.

## 7) Useful commands

- Run one file:
  - `php artisan test tests/Feature/YourTestFile.php`
- Run full suite:
  - `php artisan test`
