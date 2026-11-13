<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasEditPanel;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasFirstDraftPanel;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\ValueObjects\Spacing;

class ReelStudio extends Screen
{
    use HasFirstDraftPanel, HasEditPanel;

    protected Container $reel_scene_viewer_slot;

    protected Container $first_draft_panel;

    protected Container $edit_panel;

    protected int $state_post_id = 0;

    protected int $state_current_scene_index = 0;

    protected bool $state_first_draft_generated = false;

    /** @var array<int, array<string, mixed>> */
    protected array $state_scenes = [];

    public function __construct(
        protected PostServiceInterface $postService
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    protected function startingUI(int $post_id = 0): Container
    {
        $rawPostId = request()->query('post_id', request('post_id', 0));
        $this->state_post_id = $post_id > 0 ? $post_id : (is_numeric($rawPostId) ? (int) $rawPostId : 0);
        if ($this->state_post_id > 0) {
            $this->loadScenesFromPost($this->state_post_id);
        }

        $this->buildFirstDraftPanel();
        $this->buildEditReelPanel();

        $this->first_draft_panel->visible(true);
        $this->edit_panel->visible(false);

        $this->reel_scene_viewer_slot
            ->plain()
            ->padding(Spacing::zero());

        return $this->layoutContainer();
    }

    protected function postLoadUI(int $post_id = 0): void
    {
        if ($post_id > 0) {
            $this->state_post_id = $post_id;
            $this->loadScenesFromPost($this->state_post_id);
        }

        $this->updateScenePreview();
    }

    public function onAiFirstStructureCompleted(int $postId, string $json): void
    {
        $this->edit_panel->visible(true);
        $this->first_draft_panel->visible(false);
        $this->reel_scene_viewer_slot->embed(ReelSceneViewer::class, [
            'post_id' => $postId,
        ]);
    }

    protected function loadScenesFromPost(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }

        $post = $this->postService->findById($postId);
        if ($post !== null && !empty($post->aiScript)) {
            $decoded = json_decode((string) $post->aiScript, true);
            if (is_array($decoded) && !empty($decoded['scenes'])) {
                $this->state_scenes = $decoded['scenes'];

                return;
            }
        }
    }

    protected function updateScenePreview(): void
    {
        if (!isset($this->reel_scene_viewer_slot)) {
            return;
        }

        $params = [];
        if (!empty($this->state_scenes)) {
            $params['data'] = [
                'metadata' => [
                    'autor' => 'UNSJ',
                    'tema' => 'Reel Preview',
                    'duracion_total_segundos' => 30,
                ],
                'scenes' => $this->state_scenes,
            ];
            $params['initial_scene'] = $this->state_current_scene_index;
        } elseif ($this->state_post_id > 0) {
            $params['post_id'] = $this->state_post_id;
        }

        $this->reel_scene_viewer_slot->embed(ReelSceneViewer::class, $params);
    }
}
