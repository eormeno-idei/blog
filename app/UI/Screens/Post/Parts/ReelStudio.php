<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostServiceInterface;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasEditPanel;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasFirstDraftPanel;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Illuminate\Support\Facades\Auth;

class ReelStudio extends Screen
{
    use HasEditPanel, HasFirstDraftPanel;

    protected static bool $autoTranslate = true;

    protected Container $cnt_reel_scene_viewer_slot;

    protected Container $cnt_first_draft_panel;

    protected Container $cnt_edit_panel;

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

        $this->cnt_first_draft_panel->visible(! $hasDraft);
        $this->cnt_edit_panel->visible($hasDraft);

        $this->updateScenePreview($post_id);

        return $this->layoutContainer();
    }

    protected function postLoadUI(): void
    {
        $this->selectInitialHistoryItem();
    }

    public function onGeneratingReel(int $postId): void
    {
        $this->cnt_reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'generating_script',
            'script' => [],
        ]);
    }

    public function onAiFirstStructureCompleted(int $postId, array|string|null $script = null, ?string $json = null): void
    {
        $resolvedScript = $script;
        if (empty($resolvedScript) && ! empty($json)) {
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
