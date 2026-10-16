<?php

// @usim: feature="core", type="layout"

namespace App\UI\Layouts;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class MainLayout extends AbstractLayout
{
    /**
     * Build the layout structure wrapping the screen content.
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::START)
            ->alignItems(AlignItems::STRETCH)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0))
            ->width(Size::full())
            ->minHeight(Size::vh(100));

        // 1. Top menu slot container
        $mainMenuContainer = UI::container('main_menu_container', static::class)
            ->plain()
            ->width(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->mainMenuContainer = $mainMenuContainer;
        $this->registerSlot('top_menu', $mainMenuContainer);
        $this->registerSlot('menu', $mainMenuContainer);

        $container->add($mainMenuContainer);

        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $hostScreen = self::getActiveHostScreen();
        $menuScreen = ($hostScreen !== null ? $hostScreen::getMenuScreen() : null)
            ?? static::getMenuScreen()
            ?? $usimConfig->defaultMenuScreen;

        /** @var class-string<Screen>|null $menuClass */
        $menuClass = is_subclass_of($menuScreen, Screen::class)
            ? $menuScreen
            : Screen::resolveScreenClassFromSlug($menuScreen);

        if ($menuClass !== null) {
            $this->setActiveScreen('top_menu', $menuClass);
            Screen::embedInto($menuClass, $mainMenuContainer);
        }

        // 2. Main content slot container
        $contentContainer = UI::container('content_container', static::class)
            ->plain()
            ->width(Size::full())
            ->flexGrow(1);

        $this->contentContainer = $contentContainer;
        $this->registerSlot('main', $contentContainer);
        $this->registerSlot('content', $contentContainer);

        $container->add($contentContainer);
    }
}
