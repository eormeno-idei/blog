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

class ForgotPassword extends Screen
{
    public static Visibility $visibility = Visibility::GUEST;

    public function __construct(
        protected PasswordResetActionInterface $passwordResetAction
    ) {}

    protected Label $lbl_result;

    protected Input $email;

    public function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('start')
            ->plain()
            ->alignItems('center')
            ->padding(Spacing::px(40))
            ->minHeight(Size::vh(100));

        // Icono superior
        $container->add(
            UI::label('lock_icon')
                ->text('🔒') // O un ícono similar
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UI::label('lbl_title')
                ->text(t('screens.auth.forgot_password.title'))
                ->style('h2')
                ->center()
                ->color('#3b82f6') // Blue to match theme
        );

        /*
        // Subtitle removed from outside to be inside the card as description
        $container->add(
            UI::label('lbl_instruction')
                ->text('Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.')
                ->style('p')
                ->center()
                ->color('#6b7280')
                ->marginTop('10px')
        );
        */

        // Card Container
        $formCard = UI::container('forgot_password_card')
            ->layout(LayoutType::VERTICAL)
            ->shadow(true)
            ->maxWidth(Size::px(600))
            ->width(Size::full())
            ->borderRadius('8px')
            ->marginTop(Spacing::px(30))
            ->padding(Spacing::px(30))
            ->gap(Spacing::px(20))
            ->backgroundColor('white')
            ->customStyle('border-left: 5px solid #3b82f6; overflow: hidden;');

        $formCard->add(
            UI::label('card_title')
                ->text(t('screens.auth.forgot_password.card_title'))
                ->style('h3')
                ->color('#1f2937')
                ->marginBottom(Spacing::px(5))
        );

        $formCard->add(
            UI::label('lbl_instruction')
                ->text(t('screens.auth.forgot_password.instruction'))
                ->style('p')
                ->color('#6b7280')
                ->marginBottom(Spacing::px(15))
        );

        $formCard->add(
            UI::input('email')
                ->label(t('screens.auth.forgot_password.email.label'))
                ->type('email')
                ->placeholder(t('screens.auth.forgot_password.email.placeholder'))
                ->width(Size::full())
        );

        $formCard->add(
            $this->lbl_result = UI::label('lbl_result')
                ->text('')
                ->visible(false)
                ->center()
        );

        $buttons = UI::container('buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent('start')
            ->gap(Spacing::px(15))
            ->marginTop(Spacing::px(10));

        $buttons->add(
            UI::button('btn_send')
                ->label(t('screens.auth.forgot_password.actions.send'))
                ->style('primary')
                ->action('send_link')
        );

        $buttons->add(
            UI::button('btn_back')
                ->label(t('screens.auth.forgot_password.actions.back'))
                ->style('outline')
                ->action('navigate_to_login')
        );

        $formCard->add($buttons);

        $container->add($formCard);
    }

    /** @param array<string, mixed> $params */
    public function onNavigateToLogin(array $params): void
    {
        $this->redirect('/auth/login');
    }

    /** @param array<string, mixed> $params */
    public function onSendLink(array $params): void
    {
        $email = is_string($params['email'] ?? null) ? $params['email'] : '';

        if (empty($email)) {
            if (isset($this->lbl_result)) {
                $this->lbl_result->text(t('screens.auth.forgot_password.validation.email_required'))->style('error')->visible(true);
            }

            return;
        }

        try {
            $result = $this->passwordResetAction->sendResetLink($email);

            if ($result->isSuccess()) {
                if (isset($this->lbl_result)) {
                    $this->lbl_result->text(t('screens.auth.forgot_password.success'))->style('success')->visible(true);
                }
                $this->toast(t('screens.auth.forgot_password.toast.sent'), 'success');
                if (isset($this->email)) {
                    $this->email->value('');
                }
            } else {
                if (isset($this->lbl_result)) {
                    $this->lbl_result->text($result->message)->style('error')->visible(true);
                }
                $this->toast($result->message, 'error');
            }

        } catch (\Throwable $e) {
            if (isset($this->lbl_result)) {
                $this->lbl_result->text(t('screens.auth.forgot_password.errors.connection', ['message' => $e->getMessage()]))->style('error')->visible(true);
            }
        }
    }
}
