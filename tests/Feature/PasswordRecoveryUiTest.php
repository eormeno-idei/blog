<?php

// @usim: feature="auth", type="test"

use App\Models\User;
use App\UI\Screens\Auth\ForgotPassword;
use App\UI\Screens\Auth\ResetPassword;
use Idei\Usim\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

it('sends reset-password email from forgot-password screen', function () {
    $originalLocale = app()->getLocale();

    /** @var TestCase $this */
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);
        Notification::fake();

        $user = User::factory()->create([
            'email' => "forgot.ui.{$locale}@example.com",
        ]);

        $ui = uiScenario($this, ForgotPassword::class, ['reset' => true]);

        $response = $ui->click('btn_send', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        expect($response->json('toast.type'))->toBe('success');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('resets password from the link received by email after validations pass', function () {
    $originalLocale = app()->getLocale();
    /** @var TestCase $this */
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);
        Notification::fake();

        $user = User::factory()->create([
            'email' => "reset.ui.{$locale}@example.com",
            'password' => bcrypt('old-password-123'),
        ]);

        // Request reset link from UI.
        uiScenario($this, ForgotPassword::class, ['reset' => true])
            ->click('btn_send', ['email' => $user->email])
            ->assertOk();

        $resetUrl = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user, &$resetUrl): bool {
                $mailMessage = $notification->toMail($user);
                $url = $mailMessage->viewData['resetUrl'] ?? null;

                if (! is_string($url) || $url === '') {
                    return false;
                }

                $resetUrl = $url;

                return true;
            }
        );

        expect($resetUrl)->toStartWith('http');

        $parsedUrl = parse_url((string) $resetUrl);
        $query = [];
        parse_str($parsedUrl['query'] ?? '', $query);

        $resetUi = uiScenario($this, ResetPassword::class, array_merge($query, ['reset' => true]));

        // Ensure client-side validation blocks invalid payload first.
        $mismatchResponse = $resetUi->click('btn_reset', array_merge($query, [
            'reset_token' => (string) ($query['token'] ?? ''),
            'reset_email' => (string) ($query['email'] ?? ''),
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ]));

        $mismatchResponse->assertOk();
        expect($mismatchResponse->json('toast.type'))->toBe('error');
        expect($mismatchResponse->json('toast.message'))->toBe(t('screens.auth.reset_password.validation.mismatch'));

        // Submit valid data and verify password update.
        $validResponse = $resetUi->click('btn_reset', array_merge($query, [
            'reset_token' => (string) ($query['token'] ?? ''),
            'reset_email' => (string) ($query['email'] ?? ''),
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]));

        $validResponse->assertOk();
        expect($validResponse->json('toast.type'))->toBe('success');
        expect($validResponse->json('toast.message'))->toBe(t('screens.auth.reset_password.toast.success'));
        expect($validResponse->json('navigate.url') ?? $validResponse->json('redirect'))->toBe('/auth/login');

        $user->refresh();
        expect(Hash::check('new-password-123', (string) $user->password))->toBeTrue();
        expect(Hash::check('old-password-123', (string) $user->password))->toBeFalse();

        $resetUi->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('rejects expired reset links in reset-password screen', function () {
    /** @var TestCase $this */
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $user = User::factory()->create([
            'email' => "expired.reset.{$locale}@example.com",
        ]);

        $expiredUrl = URL::temporarySignedRoute('ui.catchall', now()->subMinute(), [
            'screen' => 'auth/reset-password',
            'token' => 'expired-token',
            'email' => $user->email,
        ]);

        $parsedUrl = parse_url($expiredUrl);
        $query = [];
        parse_str($parsedUrl['query'] ?? '', $query);

        $resetUi = uiScenario($this, ResetPassword::class, array_merge($query, ['reset' => true]));

        $response = $resetUi->click('btn_reset', array_merge($query, [
            'reset_token' => (string) ($query['token'] ?? ''),
            'reset_email' => (string) ($query['email'] ?? ''),
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]));

        $response->assertOk();
        expect($response->json('toast.type'))->toBe('error');
        expect($response->json('toast.message'))->toBe(t('screens.auth.reset_password.errors.link_expired'));

        $result = $resetUi->component('lbl_result')->data();
        expect((string) ($result['text'] ?? ''))->toBe(t('screens.auth.reset_password.errors.link_expired'));

        $user->refresh();
        expect(Hash::check('new-password-123', (string) $user->password))->toBeFalse();

        $resetUi->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
