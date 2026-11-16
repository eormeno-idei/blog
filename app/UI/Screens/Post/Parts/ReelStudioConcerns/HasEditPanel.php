<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use App\DTOs\Posts\PostHistoryEventDTO;
use App\Services\Post\PostHistoryService;
use BackedEnum;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait HasEditPanel
{
    protected NewLabel $lbl_post_title;

    protected Container $cnt_status_row;

    protected NewLabel $lbl_status_prefix;

    protected NewLabel $lbl_status_badge;

    protected ItemList $lst_history;

    protected Container $cnt_prompt_input_row;

    protected Input $input_prompt;

    protected Button $btn_generate_reel;

    /**
     * Configure the edit panel and attach its declarative layout.
     */
    protected function buildEditReelPanel(int $post_id): Container
    {
        $this->configureStatusRow();
        $this->configureHistoryList($post_id);
        $this->configurePromptInputRow();

        $post = $this->postService->findById($post_id);
        if ($post !== null && ! empty($post->title)) {
            $this->lbl_post_title->text($post->title);
        }
        if ($post !== null) {
            $this->lbl_status_badge->text(strtoupper($post->statusValue()));
        }

        $container = $this->edit_panel
            ->layoutFile('reel-studio-edit.layout.yaml');

        $this->selectInitialHistoryItem();

        return $container;
    }

    /**
     * Configure horizontal status row container.
     */
    protected function configureStatusRow(): Container
    {
        return $this->cnt_status_row
            ->appearance('elevated')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::xs())
            ->add(
                $this->lbl_status_prefix
                    ->text($this->t('.status_prefix', [], 'ESTADO:'))
                    ->bold()
                    ->small()
            )
            ->add(
                $this->lbl_status_badge
                    ->text($this->t('.status_badge', [], 'BORRADOR'))
                    ->pill()
                    ->secondary()
                    ->small()
            );
    }

    protected function configurePromptInputRow(): Container
    {
        return $this->cnt_prompt_input_row
            ->add(
                $this->input_prompt
                    ->placeholder(t('.prompt_placeholder'))
                    ->width(Size::half())
            )->add(
                $this->btn_generate_reel
                    ->icon('send')
                    ->style('primary')
            );
    }

    /**
     * Configure history list component with service wiring and context parameters.
     */
    protected function configureHistoryList(int $post_id): void
    {
        $this->lst_history
            ->selectionMode(SelectionMode::SINGLE)
            ->parameters(['post_id' => $post_id])
            ->service(PostHistoryService::class);
    }

    /**
     * Select initial history event in the list if none is currently selected.
     */
    protected function selectInitialHistoryItem(): void
    {
        if (empty($this->lst_history->getSelectedIds())) {
            $items = $this->lst_history->getItems();
            if (! empty($items)) {
                $this->lst_history->select([(string) $items[0]['id']]);
            }
        }
    }

    /**
     * Load history events for the current post, delegating to the Listable service.
     */
    protected function loadHistoryItems(?int $postId = null): void
    {
        $resolvedPostId = $postId ?? ($this->state_post_id ?? 0);
        $this->configureHistoryList($resolvedPostId);
        $this->selectInitialHistoryItem();
    }

    /**
     * Programmatically refresh history items from the PostHistoryService.
     */
    public function refreshHistoryList(?int $postId = null): void
    {
        $context = $postId !== null ? ['post_id' => $postId] : [];
        if ($postId !== null) {
            $this->lst_history->parameters($context);
        }

        $this->lst_history->refreshFromService($context);
        $this->selectInitialHistoryItem();
    }

    /**
     * Map a domain PostHistoryEventDTO into an ItemList item representation.
     *
     * @return array<string, mixed>
     */
    protected function formatHistoryEventItem(PostHistoryEventDTO $event): array
    {
        return app(PostHistoryService::class)
            ->formatEventToListItem($event)
            ->toArray();
    }

    /**
     * Format metadata line for a generated version event.
     */
    protected function formatEventMetadata(PostHistoryEventDTO $event): string
    {
        $meta = [];
        if ($event->based_on_history_id) {
            $meta[] = "Basada en v{$event->based_on_history_id}";
        }
        if (! empty($event->reel_script)) {
            $meta[] = 'Generada por IA';
        }

        return implode(' · ', $meta);
    }

    /**
     * Handle version history item selection.
     *
     * @param  array<int, string|int>  $selected
     */
    public function onSelectHistoryEvent(string|int|null $id = null, array $selected = []): void
    {
        $selectedId = $id ?? ($selected[0] ?? null);
        if ($selectedId !== null) {
            $this->lst_history->select([(string) $selectedId]);
        }
    }
}
