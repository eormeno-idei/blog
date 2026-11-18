<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Jobs\GenerateReelStructureJob;
use App\Services\Post\PostHistoryService;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Screen;
use Illuminate\Support\Facades\Auth;

class ReelStudio extends Screen
{
    protected static bool $autoTranslate = true;

    protected Container $cnt_reel_scene_viewer_slot;

    protected Container $cnt_first_draft_panel;

    protected Container $cnt_edit_panel;

    protected Button $btn_generate_first_structure;
    protected Input $input_prompt;
    protected Button $btn_generate_reel;

    protected NewLabel $lbl_post_title;

    protected NewLabel $lbl_status_badge;

    protected ItemList $lst_history;

    public function __construct(
        protected PostServiceInterface $postService,
        protected PostHistoryServiceInterface $postHistoryService,
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    protected function startingUI(int $post_id = 0): Container
    {
        $this->buildEditReelPanel($post_id);

        $hasDraft = $this->postHistoryService->hasFirstDraftEvent(Auth::user(), $post_id);

        $this->cnt_first_draft_panel->visible(!$hasDraft);
        $this->cnt_edit_panel->visible($hasDraft);

        $this->updateScenePreview($post_id);

        return $this->layoutContainer();
    }

    protected function postLoadUI(): void
    {
        $this->selectInitialHistoryItem();
    }

    public function onGenerateFirstStructure(int $postId, ?string $txt_initial_instructions = null): void
    {
        $post = $this->postService->findById($postId);
        $this->btn_generate_first_structure->enabled(false);
        $this->startReelGenerationJob($post, $txt_initial_instructions);
    }

    public function onGenerateReel(int $post_id, ?string $input_prompt = null): void
    {
        if (empty($input_prompt)) {
            return;
        }
        $post = $this->postService->findById($post_id);
        $this->input_prompt->disabled();
        $this->btn_generate_reel->disabled();
        $this->startReelGenerationJob($post, $input_prompt);
    }

    protected function startReelGenerationJob(PostDTO $post, ?string $initialInstructions): void
    {
        $new_generating_event = $this->postHistoryService->requestGenerateReelScript(
            user: Auth::user(),
            postId: $post->id,
            extra_instructions: $initialInstructions
        );

        GenerateReelStructureJob::dispatch($new_generating_event, $post->content);

        $this->startTimer('tmr_reel_poll');
    }

    public function onCheckReelStatus(int $post_id): void
    {
        $status = $this->postHistoryService->getLatestReelEvent($post_id);

        match ($status->event_type) {
            PostHistoryEventType::GENERATING_SCRIPT => $this->handleGeneratingReel(),
            PostHistoryEventType::VERSION_GENERATED => $this->handleReelCompleted($status),
            PostHistoryEventType::GENERATION_FAILED => $this->handleFailedStatus($status),
            default => null,
        };
    }

    private function handleFailedStatus(PostHistoryEventDTO $status): void
    {
        $this->stopTimer('tmr_reel_poll');
        $errorMessage = $status->summary ?? $this->t('.toast.unknown_error');
        $this->toast($this->t('.toast.generation_failed', ['message' => $errorMessage]), 'error');
    }

    private function handleGeneratingReel(): void
    {
        $this->cnt_reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'generating_script',
            'script' => [],
        ]);
    }

    public function handleReelCompleted(PostHistoryEventDTO $status): void
    {
        $this->stopTimer('tmr_reel_poll');
        $postId = $status->post_id;
        $resolvedScript = $status->reel_script;
        if (empty($resolvedScript) && !empty($json)) {
            $decoded = json_decode($json, true);
            $resolvedScript = is_array($decoded) ? $decoded : [];
        } elseif (is_string($resolvedScript)) {
            $decoded = json_decode($resolvedScript, true);
            $resolvedScript = is_array($decoded) ? $decoded : [];
        }

        $this->cnt_edit_panel->visible(true);
        $this->cnt_first_draft_panel->visible(false);
        $this->input_prompt->disabled(false);
        $this->input_prompt->value('');
        $this->btn_generate_reel->enabled();
        $this->lst_history->select([(string) $status->id]);

        $this->loadHistoryItems($postId);
        $this->cnt_reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'completed',
            'script' => $resolvedScript ?? [],
        ]);
    }

    protected function updateScenePreview(int $post_id, ?int $event_id = null): void
    {
        $lastEvent = $event_id ?
            $this->postHistoryService->findPostEventHistoryById(Auth::user(), $event_id) :
            $this->postHistoryService->getLastGeneratedVersionEvent(Auth::user(), $post_id);

        $this->cnt_reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'completed',
            'script' => $lastEvent?->reel_script ?? [],
        ]);
    }

    protected function buildEditReelPanel(int $post_id): void
    {
        $this->configureHistoryList($post_id);

        $post = $this->postService->findById($post_id);
        if ($post !== null && !empty($post->title)) {
            $this->lbl_post_title->text($post->title);
        }
        if ($post !== null) {
            $this->lbl_status_badge->text(strtoupper($post->statusValue()));
        }
    }

    protected function configureHistoryList(int $post_id): void
    {
        $this->lst_history->parameters(['post_id' => $post_id]);
    }

    protected function selectInitialHistoryItem(): void
    {
        if (empty($this->lst_history->getSelectedIds())) {
            $items = $this->lst_history->getItems();
            if (!empty($items)) {
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
        if (!empty($event->reel_script)) {
            $meta[] = 'Generada por IA';
        }

        return implode(' · ', $meta);
    }

    public function onSelectHistoryEvent(string|int|null $id = null, array $selected = []): void
    {
        $this->updateScenePreview(0, $id);
    }
}
