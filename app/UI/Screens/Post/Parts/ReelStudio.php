<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostServiceInterface;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasEditPanel;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasFirstDraftPanel;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReelStudio extends Screen
{
    use HasEditPanel, HasFirstDraftPanel;

    protected Container $reel_scene_viewer_slot;

    protected Container $first_draft_panel;

    protected Container $edit_panel;

    protected int $state_current_scene_index = 0;

    /** @var array<int, array<string, mixed>> */
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
        $this->loadScenesFromPost($post_id);
        $this->buildFirstDraftPanel();
        $this->buildEditReelPanel($post_id);

        $hasDraft = $this->postHistoryService->hasFirstDraftEvent(Auth::user(), $post_id);

        $this->first_draft_panel->visible(! $hasDraft);
        $this->edit_panel->visible($hasDraft);

        $this->reel_scene_viewer_slot
            ->plain()
            ->padding(Spacing::zero());

        return $this->layoutContainer();
    }

    protected function postLoadUI(int $post_id = 0): void
    {
        $this->loadScenesFromPost($post_id);
        $this->updateScenePreview($post_id);
    }

    public function onGeneratingReel(int $postId): void
    {
        $this->reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
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

        $this->edit_panel->visible(true);
        $this->first_draft_panel->visible(false);
        $this->reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'completed',
            'script' => $resolvedScript ?? [],
        ]);
    }

    protected function loadScenesFromPost(int $postId): void
    {
        $lastEvent = $this->postHistoryService->getLastGeneratedVersionEvent(Auth::user(), $postId);
        $this->state_scenes = $lastEvent?->reel_script ?? [];
    }

    protected function updateScenePreview(int $post_id): void
    {
        $params = [];
        if (! empty($this->state_scenes)) {
            $params = [
                'metadata' => [
                    'autor' => 'UNSJ',
                    'tema' => 'Reel Preview',
                    'duracion_total_segundos' => 30,
                ],
                'scenes' => $this->state_scenes,
            ];
        }

        $this->reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'status' => 'completed',
            'script' => $this->state_scenes
        ]);
    }
}
