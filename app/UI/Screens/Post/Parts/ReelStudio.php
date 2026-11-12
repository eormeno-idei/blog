<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Jobs\StructureReelScriptJob;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasEditPanel;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasFirstDraftPanel;
use Idei\Usim\Components\Container;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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

    protected function executeStructureReel(string $instructions, string $scope): void
    {
        $postId = $this->resolvePostId();
        if ($postId <= 0) {
            // Standalone or demo fallback: Load structure from _data.json if exists
            if (File::exists(base_path('_data.json'))) {
                $decoded = json_decode(File::get(base_path('_data.json')), true);
                if (is_array($decoded) && !empty($decoded['scenes'])) {
                    $this->state_scenes = $decoded['scenes'];
                    $this->updateScenePreview();
                    $this->toast('Estructura de Reel generada exitosamente.', 'success');

                    $this->state_first_draft_generated = true;
                    $this->first_draft_panel->visible(false);
                    $this->edit_panel->visible(true);

                    return;
                }
            }

            $this->toast('Post no identificado.', 'warning');

            return;
        }

        $post = $this->postService->findById($postId);
        if ($post === null) {
            $this->toast('Post no encontrado.', 'error');

            return;
        }

        $notes = trim((string) ($post->content ?: $post->title));
        $previousJson = $post->aiScript;
        if ($previousJson === null && !empty($this->state_scenes)) {
            $previousJson = json_encode([
                'metadata' => ['autor' => 'UNSJ', 'tema' => $post->title, 'duracion_total_segundos' => 30],
                'scenes' => $this->state_scenes,
            ], JSON_UNESCAPED_UNICODE);
        }

        $feedback = null;
        if ($instructions !== '') {
            $feedback = "Instrucciones del borrador: {$instructions}";
        }

        try {
            $job = new StructureReelScriptJob(
                postId: $postId,
                notes: $notes,
                previousJson: $previousJson,
                userFeedback: $feedback,
                versionNumber: 1,
            );
            $job->handle();
        } catch (\Throwable $e) {
            Log::warning('[ReelStudio] StructureReelScriptJob failed: ' . $e->getMessage());
        }

        $refreshedPost = $this->postService->findById($postId);
        $aiScript = $refreshedPost !== null ? $refreshedPost->aiScript : null;

        if ($aiScript !== null && $aiScript !== '') {
            $decoded = json_decode($aiScript, true);
            if (is_array($decoded) && !empty($decoded['scenes'])) {
                $this->state_scenes = $decoded['scenes'];
            }
        } else {
            $this->applyLocalFallbackModification($instructions, $scope, $post);
            $scriptJson = json_encode([
                'metadata' => [
                    'autor' => 'UNSJ',
                    'tema' => $post->title,
                    'duracion_total_segundos' => 30,
                ],
                'scenes' => $this->state_scenes,
            ], JSON_UNESCAPED_UNICODE);

            $this->postService->updatePost($postId, [
                'ai_script' => $scriptJson,
            ], Auth::user());
        }

        $this->updateScenePreview();
        $this->toast('Reel estructurado exitosamente.', 'success');

        event(new UsimEvent('post_changed', [
            'postId' => $postId,
            'stepIndex' => 2,
        ]));
    }

    protected function applyLocalFallbackModification(string $instructions, string $scope, ?PostDTO $post = null): void
    {
        if (empty($this->state_scenes)) {
            $this->state_scenes = $this->getDefaultScenes($post);
        }

        if ($instructions !== '') {
            if (preg_match('/["\']([^"\']+)["\']/', $instructions, $matches)) {
                $this->state_scenes[0]['text_content']['headline'] = $matches[1];
            }
        }
    }

    protected function resolvePostId(): int
    {
        if ($this->state_post_id > 0) {
            return $this->state_post_id;
        }

        $fromRequest = request()->query('post_id', request('post_id', 0));
        if (is_numeric($fromRequest) && (int) $fromRequest > 0) {
            $this->state_post_id = (int) $fromRequest;

            return $this->state_post_id;
        }

        return 0;
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

    protected function getDefaultScenes(?PostDTO $post = null): array
    {
        $title = $post !== null && !empty(trim($post->title))
            ? trim($post->title)
            : 'Título del Reel';

        return [
            [
                'scene_index' => 1,
                'layout_template' => 'title_hero',
                'duration_seconds' => 6,
                'text_content' => [
                    'headline' => $title,
                    'subheadline' => 'Hubo un problema al generar el Reel',
                    'body' => ['API Key vencida'],
                ],
            ],
        ];
    }
}
