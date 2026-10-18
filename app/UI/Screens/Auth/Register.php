<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Role\RoleService;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Contracts\RegisterActionInterface;
use Idei\Usim\DTOs\RegistrationResult;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Screen;
use Idei\Usim\Support\FakeDataHelper;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class Register extends Screen
{
    public function __construct(
        protected RegisterActionInterface $registerAction
    ) {}

    public static Visibility $visibility = Visibility::GUEST;

    protected Label $lbl_register_result;
    protected string $store_token = '';
    protected bool $state_ask_for_role = false;

    public static function authorize(): bool
    {
        // Accessible to guests (users not authenticated) or admins managing users
        return !self::requireAuth() || self::requirePermission('admin.users_manager.access');
    }

    public static function getMenuLabel(): string
    {
        return 'Registrarse';
    }

    public static function getMenuIcon(): ?string
    {
        return '📝';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $name = '';
        $email = '';
        $password = '';
        $password_confirmation = '';
        /** @var list<string> $selectedRoles */
        $selectedRoles = [config('usim.default_registering_role', 'registered')];

        $fakeData = (bool) ($params['fakeData'] ?? (config('app.env') === 'local'));
        $askForRole = (bool) ($params['askForRole'] ?? false);
        $this->state_ask_for_role = $askForRole;

        if ($fakeData) {
            $roleService = app(RoleService::class);
            $availableRoles = array_map(
                static fn(UsimRole $role): string => $role->name,
                $roleService->getAllowedRoles()
            );
            if (empty($availableRoles)) {
                $availableRoles = ['user', 'admin'];
            }

            $userData = FakeDataHelper::userData($availableRoles);
            $name = $userData['name'];
            $email = $userData['email'];
            $password = $userData['password'];
            $password_confirmation = $userData['password_confirmation'];
            $selectedRoles = [$userData['role']];
        }

        $wrapper = UI::container('register_wrapper')
            ->card()
            ->minWidth(Size::px(400))
            ->maxWidth(Size::px(490))
            ->width(Size::full())
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(8));

        $card = UI::container('register_card')
            ->plain()
            ->width(Size::full())
            ->padding(Spacing::px(5));

        // Header
        $header = UI::container('register_modal_header')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->margin(Spacing::px(5));

        $header->add(
            UI::label('lbl_title')
                ->text(t('screens.auth.register.title'))
                ->style('h2')
        );

        $header->add(
            UI::button('btn_close_modal')
                ->label('✕')
                ->action('close_register_dialog')
                ->style('secondary')
                ->variant('ghost')
                ->plain()
        );

        $card->add($header);

        // Name input
        $card->add(
            UI::input('name')
                ->label(t('modal.register_dialog.name.label'))
                ->placeholder(t('modal.register_dialog.name.placeholder'))
                ->required(true)
                ->value($name)
                ->autocomplete('off')
                ->width(Size::full())
        );

        // Email input
        $card->add(
            UI::input('email')
                ->label(t('modal.register_dialog.email.label'))
                ->placeholder(t('modal.register_dialog.email.placeholder'))
                ->type('email')
                ->required(true)
                ->value($email)
                ->autocomplete('off')
                ->width(Size::full())
        );

        // Password input
        $card->add(
            UI::input('password')
                ->label(t('modal.register_dialog.password.label'))
                ->type('password')
                ->placeholder(t('modal.register_dialog.password.placeholder'))
                ->required(true)
                ->value($password)
                ->autocomplete('new-password')
                ->width(Size::full())
        );

        // Password confirmation input
        $card->add(
            UI::input('password_confirmation')
                ->label(t('modal.register_dialog.confirm_password.label'))
                ->type('password')
                ->placeholder(t('modal.register_dialog.confirm_password.placeholder'))
                ->required(true)
                ->value($password_confirmation)
                ->autocomplete('new-password')
                ->width(Size::full())
        );

        if ($askForRole) {
            $roleService = app(RoleService::class);
            $roles = $roleService->getAllowedRoles();

            /** @var list<array{value: string, label: string}> $roleOptions */
            $roleOptions = array_map(static fn(UsimRole $role): array => [
                'value' => $role->name,
                'label' => t("role.{$role->name}.name"),
            ], $roles);

            if (empty($roleOptions)) {
                $roleOptions = [
                    ['value' => 'user', 'label' => t('role.user.name')],
                    ['value' => 'admin', 'label' => t('role.admin.name')],
                ];
            }

            // Role checkbox list
            $card->add(
                UI::checkbox('roles')
                    ->label(t('modal.register_dialog.role.label'))
                    ->options($roleOptions)
                    ->vertical()
                    ->selectedValues($selectedRoles)
                    ->required(true)
            );

            // Checkbox for sending verification email
            $card->add(
                UI::checkbox('send_verification_email')
                    ->label(t('modal.register_dialog.send_verification_email'))
                    ->checked(true)
            );
        } else {
            // Checkbox for accepting terms and conditions
            $card->add(
                UI::checkbox('accept_terms')
                    ->label(t('modal.register_dialog.accept_terms'))
                    ->checked(config('app.env') === 'local')
                    ->required(true)
            );

            // Button to read terms and conditions
            $card->add(
                UI::button('btn_terms')
                    ->label(t('modal.register_dialog.read_terms'))
                    ->style('link')
                    ->action('open_terms_and_conditions')
            );
        }

        // Result / Error label
        $this->lbl_register_result = UI::label('lbl_register_result')->text('');
        $card->add($this->lbl_register_result);

        // Buttons container
        $buttonsContainer = UI::container('register_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->plain()
            ->gap(Spacing::px(10))
            ->padding(Spacing::each(Spacing::px(10), Spacing::zero()));

        $buttonsContainer->add(
            UI::button('btn_cancel_register')
                ->label(t('modal.register_dialog.cancel'))
                ->style('secondary')
                ->action('close_register_dialog')
        );

        $buttonsContainer->add(
            UI::button('btn_submit_register')
                ->label(t('modal.register_dialog.submit'))
                ->style('primary')
                ->action('submit_register')
        );

        $card->add($buttonsContainer);

        $wrapper->add($card);
        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        if (isset($this->lbl_register_result)) {
            $this->lbl_register_result->text('')->style('');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSubmitRegister(array $params): void
    {
        $isAdminFlow = (bool) ($params['ask_for_role'] ?? $params['askForRole'] ?? $this->state_ask_for_role)
            || self::requireAuth();

        $registerData = RegisterRequest::toRegisterData($params, askForRole: $isAdminFlow);
        $result = $this->registerAction->execute($registerData);

        if (! $result->isSuccess()) {
            $this->handleRegistrationFailure($result, showLabelError: $registerData->acceptTerms);

            return;
        }

        $this->handleRegistrationSuccess($result, $isAdminFlow);
    }

    protected function handleRegistrationFailure(RegistrationResult $result, bool $showLabelError = true): void
    {
        $message = RegisterRequest::formatErrorMessage($result);

        $this->toast($message, type: 'error');
        if ($showLabelError && isset($this->lbl_register_result)) {
            $this->lbl_register_result->text($message)->style('error');
        }
    }

    protected function handleRegistrationSuccess(RegistrationResult $result, bool $isAdminFlow): void
    {
        $message = $result->message;
        $this->toast($message, type: 'success');
        if (isset($this->lbl_register_result)) {
            $this->lbl_register_result->text($message)->style('success');
        }

        $user = $result->user;
        if (! ($user instanceof User)) {
            return;
        }

        if ($this->isOpenedAsModal()) {
            $this->closeModal();
        }

        if ($isAdminFlow) {
            event(new UsimEvent('user_created', ['user_id' => $user->id]));

            return;
        }

        if ($result->token !== null) {
            $this->store_token = $result->token;
        }

        /** @var class-string<Screen>|string|null $homeScreen */
        $homeScreen = $result->data['home_screen'] ?? null;
        if (\is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
            Screen::make($homeScreen)->clearCachedScreenSnapshot();
            $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onOpenTermsAndConditions(array $params = []): void
    {
        $this->openModal(LegalTerms::class);
    }

    public function onCloseRegisterDialog(): void
    {
        $this->closeModal();
    }
}
