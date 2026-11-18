<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Enums\PostHistoryEventType;
use App\Jobs\GenerateReelStructureJob;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasEditPanel;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Illuminate\Support\Facades\Auth;

class ReelStudio extends Screen
{
    use HasEditPanel;

    protected static bool $autoTranslate = true;

    protected Container $cnt_reel_scene_viewer_slot;

    protected Container $cnt_first_draft_panel;

    protected Container $cnt_edit_panel;

    protected Button $btn_generate_first_structure;

    protected int $state_current_scene_index = 0;

    protected array $state_scenes = [];

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
            PostHistoryEventType::VERSION_GENERATED => $this->firstReelCompleted($status),
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

    public function firstReelCompleted(PostHistoryEventDTO $status): void
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
        $this->loadHistoryItems($postId);
        $this->cnt_reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'completed',
            'script' => $resolvedScript ?? [],
        ]);
    }

    protected function updateScenePreview(int $post_id): void
    {
        $lastEvent = $this->postHistoryService->getLastGeneratedVersionEvent(Auth::user(), $post_id);

        $this->cnt_reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'completed',
            'script' => $lastEvent?->reel_script ?? [],
        ]);
    }
}
