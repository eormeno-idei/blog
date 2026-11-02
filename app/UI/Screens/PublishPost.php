<?php

declare(strict_types=1);

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use App\UI\Screens\Post\Support\WizzardPartsLoader;

/**
 * Pantalla de asistente (Wizard) para la creación y publicación de posts en 7 pasos.
 *
 * @property Button $btn_save_post
 */
class PublishPost extends Screen
{
    protected Container $buttons_container;

    protected Container $demo_container;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
        parent::__construct();
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $rawPostId = $params['post_id'] ?? $this->getQueryParams()['post_id'] ?? null;

        if ($rawPostId === null || !is_numeric($rawPostId) || (int) $rawPostId <= 0) {
            $this->navigate(Home::class);
            return;
        }

        $post = $this->postService->findById((int) $rawPostId);

        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(1024))
            ->centerHorizontal();

        $wrapper = UI::container('wrapper')
            ->width(Size::full())
            ->gap(Spacing::px(5))
            ->padding(Spacing::zero());

        $this->demo_container = UI::container('demo_container')
            ->card()
            ->shadow(false)
            ->width(Size::full())
            ->height(Size::px(500));

        $wrapper->add($this->buttonsContainer());
        $wrapper->add($this->demo_container);
        $wrapper->add($this->buildNavigation($post));

        $container->add($this->buildHeader());
        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        $class = WizzardPartsLoader::getWizzardByIndex($this->selectedIndex());
        if ($class === null) {
            return;
        }
        $this->demo_container->embed($class);
    }

    private function buildHeader(): Container
    {
        $header = UI::container('publish_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->padding(Spacing::zero())
            ->gap(Spacing::px(5));

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        return $header;
    }

    private function buttonsContainer(): Container
    {
        $this->buttons_container = UI::container('buttons_container')
            ->layout(LayoutType::HORIZONTAL)
            ->alignContent('center')
            ->alignItems('center')
            ->gap(Spacing::px(10))
            ->plain();

        $wizzardParts = WizzardPartsLoader::getWizzardParts();

        foreach ($wizzardParts as $index => $stepClass) {
            $button = UI::button("button_$index")
                ->label($stepClass::getName())
                ->status($index === 0)
                ->style($index === 0 ? 'primary' : 'secondary');
            $id = $button->getId();
            $button->action('demo_selected', [
                'id' => $id,
                'index' => $index,
                'screen_class' => $stepClass,
            ]);
            $this->buttons_container->add($button);
        }
        return $this->buttons_container;
    }

    private function buildNavigation(PostDTO $post): Container
    {
        $navigation = UI::container('navigation')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER);

        $navigation->add(
            UI::button('btn_previous_step')
                ->label('← Paso Anterior')
                ->action('go_to_previous_step')
                ->style('secondary')
        );

        $navigation->add(
            UI::button('btn_next_step')
                ->label('Siguiente Paso →')
                ->action('go_to_next_step')
                ->style('primary')
        );

        return $navigation;
    }

    /**
     * Handle the action when a demo button is selected.
     *
     * @param  array<string, mixed> $params Parameters containing the button ID
     * and index of the selected demo
     */
    public function onDemoSelected(array $params): void
    {
        /** @var string|null $id */
        $id = $params['id'] ?? null;
        if ($id === null) {
            return;
        }

        /** @var int|null $index */
        $index = $params['index'] ?? null;
        if ($index === null) {
            return;
        }

        /** @var class-string<Screen> | null $screenClass */
        $screenClass = $params['screen_class'] ?? null;
        if ($screenClass === null) {
            return;
        }

        $this->updateButtonState($id);
        $this->demo_container->embed($screenClass);
    }

    private function updateButtonState(string|int $id): void
    {
        foreach ($this->buttons_container->getChildren() as $button) {
            if ($button instanceof Button) {
                $button->status(false);
                $button->style('secondary');
            }
        }
        $button = $this->findRootComponentAs($id, Button::class);
        if (!$button && $id !== null) {
            foreach ($this->buttons_container->getChildren() as $child) {
                if ($child instanceof Button && $child->getName() === $id) {
                    $button = $child;
                    break;
                }
            }
        }
        if ($button) {
            $button->status(true);
            $button->style('primary');
        }
    }

    private function selectedIndex(): int
    {
        foreach ($this->buttons_container->getChildren() as $index => $button) {
            if ($button instanceof Button && $button->getStatus()) {
                return $index;
            }
        }
        return 0;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCancelPost(array $params): void
    {
        $this->stopActiveReelJobPolling();
        $this->navigate(Home::class);
    }
}
