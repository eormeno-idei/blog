<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Auth;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Contracts\PasswordResetActionInterface;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class ResetPassword extends Screen
{
    public static Visibility $visibility = Visibility::GUEST;

    public function __construct(
        protected PasswordResetActionInterface $passwordResetAction
    ) {}

    protected Label $lbl_result;

    protected Input $password;

    protected Input $password_confirmation;

    public function buildBaseUI(Container $container, ...$params): void
    {
        $token = request()->query('token');
        $email = request()->query('email');

        $container
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('start')
            ->alignItems('center')
            ->plain()
            ->padding(Spacing::px(40))
            ->minHeight(Size::vh(100));

        // Icono superior
        $container->add(
            UI::label('key_icon')
                ->text('🔑')
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UI::label('lbl_title')
                ->text(t('screens.auth.reset_password.title'))
                ->style('h2')
                ->center()
                ->color('#10b981') // Green to match theme
        );

        // Subtitle moved inside card
        /*
        $container->add(
            UI::label('lbl_subtitle')
                ->text('Por favor ingresa tu nueva contraseña segura.')
                ->style('p')
                ->center()
                ->color('#6b7280')
                ->marginTop('10px')
        );
        */

        // Card Container
        $formCard = UI::container('reset_password_card')
            ->layout(LayoutType::VERTICAL)
            ->shadow(true)
            ->maxWidth(Size::px(600))
            ->width(Size::full())
            ->borderRadius('8px')
            ->marginTop(Spacing::px(30))
            ->padding(Spacing::px(30))
            ->gap(Spacing::px(20))
            ->backgroundColor('white')
            ->customStyle('border-left: 5px solid #10b981; overflow: hidden;');

        $formCard->add(
            UI::label('card_title')
                ->text(t('screens.auth.reset_password.card_title'))
                ->style('h3')
                ->color('#1f2937')
                ->marginBottom(Spacing::px(5))
        );

        $formCard->add(
            UI::label('lbl_subtitle_card')
                ->text(t('screens.auth.reset_password.instruction'))
                ->style('p')
                ->color('#6b7280')
                ->marginBottom(Spacing::px(15))
        );

        // Hidden fields for token and email
        $formCard->add(
            UI::input('reset_token')->type('hidden')->value($token ?? '')
        );
        $formCard->add(
            UI::input('reset_email')->type('hidden')->value($email ?? '')
        );

        $formCard->add(
            UI::input('password')
                ->label(t('screens.auth.reset_password.password.label'))
                ->type('password')
                ->placeholder(t('screens.auth.reset_password.password.placeholder'))
                ->required(true)
                ->width(Size::full())
        );

        $formCard->add(
            UI::input('password_confirmation')
                ->label(t('screens.auth.reset_password.confirm.label'))
                ->type('password')
                ->placeholder(t('screens.auth.reset_password.confirm.placeholder'))
                ->required(true)
                ->width(Size::full())
        );

        $formCard->add(
            UI::label('lbl_result')
                ->text('')
                ->visible(false)
                ->center()
        );

        $formCard->add(
            UI::button('btn_reset')
                ->label(t('screens.auth.reset_password.actions.submit'))
                ->style('success')
                ->action('reset_password')
                ->marginTop(Spacing::px(10))
        );

        $container->add($formCard);
    }

    /** @param array<string, mixed> $params */
    public function onResetPassword(array $params): void
    {
        $tokenRaw = $params['reset_token'] ?? $params['token'] ?? '';
        $token = is_scalar($tokenRaw) ? (string) $tokenRaw : '';
        $emailRaw = $params['reset_email'] ?? $params['email'] ?? '';
        $email = is_scalar($emailRaw) ? (string) $emailRaw : '';
        $expiresRaw = $params['expires'] ?? request()->query('expires', 0);
        $expires = is_numeric($expiresRaw) ? (int) $expiresRaw : 0;
        $passwordRaw = $params['password'] ?? '';
        $password = is_scalar($passwordRaw) ? (string) $passwordRaw : '';
        $passwordConfirmationRaw = $params['password_confirmation'] ?? '';
        $passwordConfirmation = is_scalar($passwordConfirmationRaw) ? (string) $passwordConfirmationRaw : '';

        if (empty($token) || empty($email)) {
            $this->showError(t('screens.auth.reset_password.errors.invalid_link'));

            return;
        }

        if ($expires > 0 && now()->timestamp > $expires) {
            $this->showError(t('screens.auth.reset_password.errors.link_expired'));

            return;
        }

        if (strlen($password) < 8) {
            $this->showError(t('screens.auth.reset_password.validation.min_length'));

            return;
        }

        if ($password !== $passwordConfirmation) {
            $this->showError(t('screens.auth.reset_password.validation.mismatch'));

            return;
        }

        try {
            $result = $this->passwordResetAction->resetPassword(
                token: $token,
                email: $email,
                password: $password,
                passwordConfirmation: $passwordConfirmation
            );

            if ($result->isSuccess()) {
                if (isset($this->lbl_result)) {
                    $this->lbl_result
                        ->text(t('screens.auth.reset_password.success.label'))
                        ->style('text-green-600 font-medium')
                        ->visible(true);
                }

                $this->toast(t('screens.auth.reset_password.toast.success'), 'success');

                // Navigate to login via SPA
                $this->showInto(Login::class, updateBrowserUrl: true);
            } else {
                // Extract validation errors if any
                $errors = $result->errors;
                $firstErrorGroup = reset($errors);
                $firstError = is_array($firstErrorGroup) ? ($firstErrorGroup[0] ?? $result->message) : $result->message;
                $this->showError($firstError);
            }

        } catch (\Throwable $e) {
            $this->showError(t('screens.auth.reset_password.errors.connection', ['message' => $e->getMessage()]));
        }
    }

    private function showError(string $message): void
    {
        if (isset($this->lbl_result)) {
            $this->lbl_result->text($message)->style('text-red-500 text-sm')->visible(true);
        }
        $this->toast($message, 'error');
    }
}
