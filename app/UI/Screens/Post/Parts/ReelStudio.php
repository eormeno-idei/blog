<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Jobs\StructureReelScriptJob;
use App\UI\Screens\Post\Parts\ReelSceneViewer;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasEditPanel;
use App\UI\Screens\Post\Parts\ReelStudioConcerns\HasFirstDraftPanel;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
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

    protected function buildBaseUI(Container $container, int $post_id = 0): void
    {
        $this->state_post_id = $post_id > 0 ? $post_id : (int) request()->query('post_id', request('post_id', 0));
        if ($this->state_post_id > 0) {
            $this->loadScenesFromPost($this->state_post_id);
        }

        $container
            ->width(Size::full())
            ->height(Size::full())
            ->justifyContent(JustifyContent::START)
            ->alignItems(AlignItems::CENTER)
            ->add($this->buildMainRow());
    }

    protected function postLoadUI(int $post_id = 0): void
    {
        if ($post_id > 0) {
            $this->state_post_id = $post_id;
            $this->loadScenesFromPost($this->state_post_id);
            $this->updateScenePreview();
        }
    }

    protected function buildMainRow(): Container
    {
        $mainRow = UI::container('reel_studio_main_row')
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::START)
            ->gap(Spacing::px(28))
            ->width(Size::full())
            ->maxWidth(Size::px(800))
            ->padding(Spacing::px(16));

        $mainRow->add($this->buildLeftControlsPanel());
        $mainRow->add($this->buildRightPreviewPanel());

        return $mainRow;
    }

    protected function buildLeftControlsPanel(): Container
    {
        $this->first_draft_panel = $this->buildFirstDraftPanel();
        $this->edit_panel = $this->buildEditReelPanel();

        return UI::container('left_controls_panel')
            ->add($this->first_draft_panel)
            ->add($this->edit_panel);
    }

    protected function buildRightPreviewPanel(): Container
    {
        $rightCol = UI::container('right_preview_panel')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::px(320))
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(10))
            ->padding(Spacing::zero());

        $this->reel_scene_viewer_slot = UI::container('reel_scene_viewer_slot');
        $rightCol->add($this->reel_scene_viewer_slot);

        $this->updateScenePreview();

        return $rightCol;
    }

    public function onGenerateFirstStructure(array $params = []): void
    {
        $rawInstructions = $params['initial_instructions']
            ?? (isset($this->initial_instructions) ? $this->initial_instructions->getValue() : '');
        $instructions = is_string($rawInstructions) ? trim($rawInstructions) : '';

        $this->executeStructureReel($instructions, 'reel');
    }

    public function onStructureReel(array $params = []): void
    {
        $this->onGenerateFirstStructure($params);
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
