<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Units\UsimUnitsService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Menu Screen
 *
 * Barra de navegación superior 100% SPA para el Gestor de Posts en USIM.
 */
class Menu extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $layout = null;

    public function __construct(
        protected AuthSessionService $authSessionService,
        protected UsimUnitsService $usimUnitsService
    ) {
    }

    protected MenuDropdown $main_menu;
    protected MenuDropdown $user_menu;
    protected Button $theme_toggle;
    protected string $store_theme = 'dark';
    protected string $store_token = '';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        if ($this->parent === null || $this->parent === 'main') {
            $container->parent('menu');
        }

        $container
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(5))
            ->marginBottom(Spacing::px(0));

        $this->initActiveUnit();

        $this->main_menu = $this->buildLeftMenu();
        $this->user_menu = $this->buildUserMenu();

        $this->theme_toggle = UI::button('theme_toggle')
            ->action('toggleTheme')
            ->plain();

        $rightSection = UI::container('menu_right_section')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(12))
            ->marginLeft(Spacing::auto());

        $rightSection->add($this->theme_toggle);
        $rightSection->add($this->user_menu);

        $container->add($this->main_menu);
        $container->add($rightSection);
        $this->updateThemeButton();
    }

    private function updateThemeButton(): void
    {
        $icon = $this->store_theme === 'light' ? 'theme-icon-light' : 'theme-icon-dark';
        $this->theme_toggle->icon("/vendor/idei/usim/images/$icon.svg");
        $this->theme_toggle->iconColor('var(--usim-menu-trigger-text)');
        $this->theme_toggle->iconSize(24);
        $this->theme_toggle->tooltip(t('screens.menu.theme_switch_to', ['theme' => $this->store_theme === 'light' ? 'dark' : 'light']));
    }

    /**
     * Alterna entre modo claro y oscuro sin recargar la página.
     *
     * @param array<string, mixed> $params
     */
    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->updateThemeButton();
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    private function initActiveUnit(): void
    {
        /** @var Collection<int, UsimUnit> $units */
        $units = $this->usimUnitsService->getAvailableUnits();
        if ($units->isEmpty()) {
            UIStateManager::setActiveUnit('lobby');
            return;
        }

        $cachedUnit = UIStateManager::getActiveUnit();
        if ($cachedUnit !== null && $units->contains('slug', $cachedUnit)) {
            $unit = $units->firstWhere('slug', $cachedUnit);
            if ($unit instanceof UsimUnit) {
                setPermissionsTeamId($unit->id);
            }
            return;
        }

        $first = $units->first();
        if ($first instanceof UsimUnit) {
            UIStateManager::setActiveUnit($first->slug);
            setPermissionsTeamId($first->id);
        }
    }

    protected function postLoadUI(): void
    {
        $this->updateThemeButton();

        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User) {
                $this->updateUserMenuTrigger($user);
                $this->initActiveUnit();
            } else {
                $this->user_menu->trigger("⚙️");
            }
            $this->populateMainMenu($this->main_menu);
            $this->populateUserMenu($this->user_menu);
        } else {
            $this->user_menu->trigger("⚙️");
            $this->populateMainMenu($this->main_menu);
            $this->populateUserMenu($this->user_menu);
        }
    }

    private function updateUserMenuTrigger(User $user): void
    {
        if ($user->profile_image) {
            $imageUrl = UploadService::fileUrl("uploads/images/$user->profile_image");
            $this->user_menu->triggerImage(
                imageUrl: $imageUrl,
                alt: $user->name,
                label: $user->name
            );
        } else {
            $this->user_menu->trigger("👤 $user->name");
        }
    }

    private function getMainMenuBuilder(): MenuBuilder
    {
        $builder = MenuBuilder::make('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(Size::px(220));

        $user = Auth::user();
        $isRegisteredOnly = $user instanceof User && $user->isRegisteredInLobby();


        $builder->screenShow(
            Home::class,
            when: !$isRegisteredOnly
        );

        $builder->screenShow(
            PublishPost::class,
            when: PublishPost::checkAccess()['allowed'] == true
        );

        $builder->screenShow(
            UsersManager::class,
            when: UsersManager::checkAccess()['allowed'] == true
        );

        $builder->separator();
        $builder->action(t('screens.menu.items.about'), 'show_about_info', [], 'ℹ️');

        return $builder;
    }

    private function buildLeftMenu(): MenuDropdown
    {
        return $this->getMainMenuBuilder()->render('main_menu');
    }

    private function populateMainMenu(MenuDropdown $menu): void
    {
        $this->getMainMenuBuilder()->populate($menu);
    }

    private function buildUserMenu(): MenuDropdown
    {
        $builder = $this->getUserMenuBuilder();
        $menu = $builder->render('user_menu');
        $menu->trigger("⚙️");
        return $menu;
    }

    private function populateUserMenu(MenuDropdown $menu): void
    {
        $builder = $this->getUserMenuBuilder();
        $builder->populate($menu);
    }

    private function getUserMenuBuilder(): MenuBuilder
    {
        $builder = MenuBuilder::make('user_menu')
            ->position('bottom-right')
            ->width(Size::px(180));

        $builder->screenShow(Login::class, modal: true, when: !Auth::check());
        $builder->screenShow(Register::class, modal: true, when: !Auth::check());
        $builder->screenShow(Profile::class, modal: true, when: Auth::check());

        $builder->action(
            t('screens.menu.items.logout'),
            'confirm_logout',
            [],
            '🚪',
            when: Auth::check()
        );

        return $builder;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowScreen(array $params): void
    {
        /** @var class-string<Screen>|string|null $screen */
        $screen = $params['screen'] ?? null;
        $modal = $params['modal'] ?? false;

        if ($screen !== null && is_subclass_of($screen, Screen::class)) {
            /** @var string|null $slot */
            $slot = isset($params['slot']) && \is_string($params['slot']) ? $params['slot'] : null;

            if ($modal) {
                $this->openModal($screen);
                return;
            }

            $this->showInto($screen, slot: $slot, updateBrowserUrl: true, force: true);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onLoggedUser(array $params): void
    {
        /** @var User|null $user */
        $user = $params['user'] ?? null;
        $unitValue = $params['unit'] ?? '';
        $homeScreen = $params['home_screen'] ?? null;
        $token = $params['token'] ?? null;

        if (\is_string($token) && $token !== '') {
            $this->store_token = $token;
        }

        if (\is_string($unitValue) && $unitValue !== '') {
            UIStateManager::setActiveUnit($unitValue);
        }

        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }

        $this->populateUserMenu($this->user_menu);
        $this->populateMainMenu($this->main_menu);

        // Screen::make(Home::class)->clearCachedScreenSnapshot();

        if (\is_string($homeScreen) && $homeScreen !== '' && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
            // Screen::make($homeScreen)->clearCachedScreenSnapshot();
            $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onConfirmLogout(array $params): void
    {
        if (UIStateManager::getClientActiveModal() !== null) {
            $this->closeModal();
        }

        $homeScreen = $this->authSessionService->logout();

        $this->toast(t('screens.menu.logout_success'), 'success');
        $this->resetToGuestState($homeScreen);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUserLoggedOut(array $params): void
    {
        if ($this->store_token === '' && !Auth::check()) {
            return;
        }

        /** @var class-string<Screen>|string|null $homeScreen */
        $homeScreen = $params['home_screen'] ?? Home::class;
        $targetClass = \is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)
            ? $homeScreen
            : Home::class;

        $this->resetToGuestState($targetClass);
    }

    private function resetToGuestState(string $homeScreen): void
    {
        $this->store_token = '';

        $this->user_menu->trigger("⚙️");
        $this->populateUserMenu($this->user_menu);
        $this->populateMainMenu($this->main_menu);

        $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowAboutInfo(array $params): void
    {
        $version = "0.7.0";
        $aboutMessage = t('screens.menu.about.message', [
            'version' => $version,
        ]);

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::INFO,
            title: t('screens.menu.about.title'),
            message: $aboutMessage,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCloseProfileDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onLogoutUser(array $params): void
    {
        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: t('screens.menu.logout_confirm.title'),
            message: t('screens.menu.logout_confirm.message'),
            confirmAction: 'confirm_logout',
            cancelAction: 'cancel_logout',
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCancelLogout(array $params): void
    {
        $this->closeModal();
    }
}
