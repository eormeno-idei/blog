<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Contracts\LoginActionInterface;
use Idei\Usim\DTOs\LoginCredentials;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Validation\ValidationException;

class Login extends Screen
{
    public function __construct(
        protected LoginActionInterface $loginAction
    ) {}

    public static Visibility $visibility = Visibility::GUEST;

    protected string $state_email = '';

    protected string $store_token = '';

    protected Label $lbl_login_result;

    public static function authorize(): bool
    {
        // This screen should only be accessible to guests,
        // i.e. users who are not authenticated.
        return ! self::requireAuth();
    }

    public static function getMenuLabel(): string
    {
        return 'Iniciar Sesión';
    }

    public static function getMenuIcon(): ?string
    {
        return '🔑';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $email = '';
        $password = '';

        if (config('app.env') === 'local') {
            // Pre-fill credentials in local environment for easier testing
            $email = empty($this->state_email)
                ? config('usim.users.root.email')
                : $this->state_email;
            $password = config('usim.users.root.password');
        }

        $wrapper = UI::container('login_wrapper')
            ->card()
            ->width(Size::full())
            ->minWidth(Size::px(350))
            ->maxWidth(Size::px(350))
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(8));

        $card = UI::container('login_card')
            ->plain()
            ->width(Size::full());

        $card->add(
            UI::input('login_email')
                ->label(t('screens.auth.login.email.label'))
                ->placeholder(t('screens.auth.login.email.placeholder'))
                ->value($email)
                ->type('email')
                ->required(true)
                ->width(Size::full())
        );

        $card->add(
            UI::input('login_password')
                ->label(t('screens.auth.login.password.label'))
                ->type('password')
                ->placeholder(t('screens.auth.login.password.placeholder'))
                ->value($password)
                ->required(true)
                ->width(Size::full())
        );

        $card->add(
            $this->lbl_login_result = UI::label('lbl_login_result')->text('')
        );

        $buttonsContainer = UI::container('login_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->plain()
            ->gap(Spacing::px(10))
            ->padding(Spacing::each(Spacing::px(20)));

        $buttonsContainer->add(
            UI::button('btn_cancel_login')
                ->label(t('screens.auth.login.actions.cancel'))
                ->style('secondary')
                ->action('close_login_dialog')
        );

        $buttonsContainer->add(
            UI::button('btn_submit_login')
                ->label(t('screens.auth.login.actions.submit'))
                ->style('primary')
                ->action('submit_login')
        );

        $card->add($buttonsContainer);

        // Forgot Password Link left-aligned below the buttons and filled with the full width of the container
        $card->add(
            UI::button('btn_forgot_password')
                ->label(t('screens.auth.login.actions.forgot_password'))
                ->style('link')
                ->action('navigate_forgot_password')
                ->width(Size::full())
        );

        $wrapper->add($card);
        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        if (isset($this->lbl_login_result)) {
            $this->lbl_login_result->text('')->style('');
        }
    }

    /** @param array<string, mixed> $params */
    public function onNavigateForgotPassword(array $params): void
    {
        $this->redirect('/auth/forgot-password');
    }

    /** @param array<string, mixed> $params */
    public function onSubmitLogin(array $params): void
    {
        try {
            $credentials = LoginRequest::validateData($params);
        } catch (ValidationException $e) {
            $message = t('service.auth.login.validation_errors');
            event(new UsimEvent('login_failed', [
                'email' => is_string($params['login_email'] ?? ($params['email'] ?? null)) ? ($params['login_email'] ?? $params['email']) : '',
                'message' => $message,
                'errors' => $e->errors(),
            ]));

            return;
        }

        $this->state_email = $credentials['email'];

        $loginCredentials = new LoginCredentials(
            email: $credentials['email'],
            password: $credentials['password'],
            remember: $credentials['remember'],
        );

        $this->loginAction->execute($loginCredentials, startSession: true);
    }

    /**
     * Handles domain event when a user logs in successfully.
     *
     * @param  array<string, mixed>  $params
     */
    public function onLoggedUser(array $params): void
    {
        $token = $params['token'] ?? null;
        if (\is_string($token) && $token !== '') {
            $this->store_token = $token;
        }

        $message = \is_string($params['message'] ?? null)
            ? $params['message']
            : t('service.auth.login.success');

        $this->toast(
            message: $message,
            type: 'success'
        );

        if (isset($this->lbl_login_result)) {
            $this->lbl_login_result->text($message)->style('success');
        }

        if ($this->isOpenedAsModal()) {
            $this->closeModal();

            return;
        }

        /** @var class-string<Screen>|string|null $homeScreen */
        $homeScreen = $params['home_screen'] ?? null;
        if (\is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
            $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
        }
    }

    /**
     * Handles domain event when a login attempt fails.
     *
     * @param  array<string, mixed>  $params
     */
    public function onLoginFailed(array $params): void
    {
        $message = \is_string($params['message'] ?? null)
            ? $params['message']
            : t('service.auth.login.invalid_credentials');

        $this->toast(
            message: $message,
            type: 'error'
        );

        if (isset($this->lbl_login_result)) {
            $this->lbl_login_result->text($message)->style('error');
        }
    }

    public function onCloseLoginDialog(): void
    {
        if ($this->isOpenedAsModal()) {
            $this->closeModal();

            return;
        }

        $this->showInto(\App\UI\Screens\Home::class, force: true, updateBrowserUrl: true);
    }
}
