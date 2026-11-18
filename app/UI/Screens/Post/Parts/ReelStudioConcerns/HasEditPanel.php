<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use App\DTOs\Posts\PostHistoryEventDTO;
use App\Services\Post\PostHistoryService;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\NewLabel;
use Illuminate\Support\Facades\Auth;

trait HasEditPanel
{
    protected NewLabel $lbl_post_title;

    protected NewLabel $lbl_status_badge;

    protected ItemList $lst_history;

    protected function buildEditReelPanel(int $post_id): void
    {
        $this->configureHistoryList($post_id);

        $post = $this->postService->findById($post_id);
        if ($post !== null && ! empty($post->title)) {
            $this->lbl_post_title->text($post->title);
        }
        if ($post !== null) {
            $this->lbl_status_badge->text(strtoupper($post->statusValue()));
        }
    }

    public function onGenerateReel(int $post_id, ?string $input_prompt = null): void
    {
        if (empty($input_prompt)) {
            return;
        }

        $this->postHistoryService->requestGenerateReelScript(Auth::user(), $post_id, $input_prompt);
    }

    protected function configureHistoryList(int $post_id): void
    {
        $this->lst_history->parameters(['post_id' => $post_id]);
    }

    protected function selectInitialHistoryItem(): void
    {
        if (empty($this->lst_history->getSelectedIds())) {
            $items = $this->lst_history->getItems();
            if (! empty($items)) {
                $this->lst_history->select([(string) $items[0]['id']]);
            }
        }
    }

    protected function loadHistoryItems(?int $postId = null): void
    {
        $resolvedPostId = $postId ?? ($this->state_post_id ?? 0);
        $this->refreshHistoryList($resolvedPostId);
    }

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
