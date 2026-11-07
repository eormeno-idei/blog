<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Jobs\StructureReelScriptJob;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReelStudio extends Screen
{
    // Left column components
    protected Textarea $ai_instructions;

    protected Button $btn_summarize;

    protected Button $btn_expand;

    protected Button $btn_reduce_time;

    protected Button $btn_increase_time;

    protected Label $scope_selector;

    protected Button $btn_structure_reel;

    // Right column components
    protected Container $scene_preview_card;

    protected Label $scene_accent;

    protected Label $scene_headline;

    protected Label $scene_subheadline;

    protected Button $btn_prev_scene;

    protected Button $scene_indicator;

    protected Button $btn_next_scene;

    // State properties
    protected int $state_post_id = 0;

    protected int $state_current_scene_index = 0;

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
        // $this->state_post_id = $post_id > 0 ? $post_id : (int) request()->query('post_id', request('post_id', 0));
        // $this->loadScenesFromPost($this->state_post_id);

        $container
            ->width(Size::full())
            ->height(Size::full())
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER)
            ->add($this->buildMainRow());
    }

    protected function postLoadUI(int $post_id = 0): void
    {
        // if ($post_id > 0) {
        //     $this->state_post_id = $post_id;
        // }
        // $this->loadScenesFromPost($this->state_post_id);
        // $this->updateScenePreview();
    }

    protected function buildMainRow(): Container
    {
        $mainRow = UI::container('reel_studio_main_row')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::START)
            ->gap(Spacing::px(28))
            ->width(Size::full())
            ->padding(Spacing::px(16));

        $mainRow->add($this->buildLeftControlsPanel());
        $mainRow->add($this->buildRightPreviewPanel());

        return $mainRow;
    }

    protected function buildLeftControlsPanel(): Container
    {
        $leftCol = UI::container('left_controls_panel')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::px(320))
            ->gap(Spacing::px(10))
            ->padding(Spacing::zero());

        $this->ai_instructions = UI::textarea('ai_instructions')
            ->placeholder('Cambiar el título por "XXXXX"')
            ->width(Size::full())
            ->height(Size::px(85));

        $this->btn_summarize = UI::button('btn_summarize')
            ->label('Resumir más')
            ->style('success')
            ->fullWidth(true)
            ->action('on_summarize');

        $this->btn_expand = UI::button('btn_expand')
            ->label('Ampliar con más detalles')
            ->style('success')
            ->fullWidth(true)
            ->action('on_expand');

        $this->btn_reduce_time = UI::button('btn_reduce_time')
            ->label('Reducir tiempo')
            ->style('primary')
            ->fullWidth(true)
            ->action('on_reduce_time');

        $this->btn_increase_time = UI::button('btn_increase_time')
            ->label('Aumentar duración')
            ->style('primary')
            ->fullWidth(true)
            ->action('on_increase_time');

        $this->scope_selector = UI::label('scope_selector')
            ->html('
                <div style="display:flex; align-items:center; gap:24px; padding: 6px 8px; font-size:14px; font-weight:500; color:#374151;">
                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="radio" name="refine_scope" value="reel" checked style="cursor:pointer; width:16px; height:16px;" /> Reel
                    </label>
                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="radio" name="refine_scope" value="scene" style="cursor:pointer; width:16px; height:16px;" /> Escena
                    </label>
                </div>
            ');

        $this->btn_structure_reel = UI::button('btn_structure_reel')
            ->label('Estructurar Reel')
            ->style('info')
            ->fullWidth(true)
            ->action('on_structure_reel');

        $leftCol->add($this->ai_instructions);
        $leftCol->add($this->btn_summarize);
        $leftCol->add($this->btn_expand);
        $leftCol->add($this->btn_reduce_time);
        $leftCol->add($this->btn_increase_time);
        $leftCol->add($this->scope_selector);
        $leftCol->add($this->btn_structure_reel);

        return $leftCol;
    }

    protected function buildRightPreviewPanel(): Container
    {
        $rightCol = UI::container('right_preview_panel')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::px(260))
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(10))
            ->padding(Spacing::zero());

        $this->scene_preview_card = UI::container('scene_preview_card')
            ->backgroundColor('#0b2545')
            ->borderRadius('8px')
            ->width(Size::px(260))
            ->height(Size::px(390))
            ->padding(Spacing::px(20))
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(16));

        $this->scene_accent = UI::label('scene_accent')
            ->text('—')
            ->color('#f59e0b')
            ->fontSize('24')
            ->bold()
            ->center();

        $this->scene_headline = UI::label('scene_headline')
            ->text('INNOVACIÓN EN SOFTWARE BACKEND-DRIVEN')
            ->color('#ffffff')
            ->fontSize('18')
            ->bold()
            ->uppercase()
            ->center();

        $this->scene_subheadline = UI::label('scene_subheadline')
            ->text('PDTS Convocatoria 2025 - Universidad Nacional de San Juan')
            ->color('#facc15')
            ->fontSize('12')
            ->center();

        $this->scene_preview_card->add($this->scene_accent);
        $this->scene_preview_card->add($this->scene_headline);
        $this->scene_preview_card->add($this->scene_subheadline);

        $navRow = UI::container('scene_nav_row')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->width(Size::px(260))
            ->gap(Spacing::px(8))
            ->padding(Spacing::zero());

        $this->btn_prev_scene = UI::button('btn_prev_scene')
            ->label('◀')
            ->style('info')
            ->variant('outline')
            ->action('on_prev_scene')
            ->enabled(false);

        $this->scene_indicator = UI::button('scene_indicator')
            ->label('Escena 1/5')
            ->variant('outline')
            ->style('secondary');

        $this->btn_next_scene = UI::button('btn_next_scene')
            ->label('▶')
            ->style('info')
            ->variant('outline')
            ->action('on_next_scene')
            ->enabled(true);

        $navRow->add($this->btn_prev_scene);
        $navRow->add($this->scene_indicator);
        $navRow->add($this->btn_next_scene);

        $rightCol->add($this->scene_preview_card);
        $rightCol->add($navRow);

        return $rightCol;
    }

    public function onPrevScene(): void
    {
        if ($this->state_current_scene_index > 0) {
            $this->state_current_scene_index--;
            $this->updateScenePreview();
        }
    }

    public function onNextScene(): void
    {
        if ($this->state_current_scene_index < count($this->state_scenes) - 1) {
            $this->state_current_scene_index++;
            $this->updateScenePreview();
        }
    }

    public function onSummarize(array $params = []): void
    {
        $scope = $params['refine_scope'] ?? 'reel';
        $instruction = $scope === 'scene'
            ? 'Resumir más el contenido de la escena actual.'
            : 'Resumir más el contenido de todo el Reel.';

        $this->ai_instructions->value($instruction);
        $this->executeStructureReel($instruction, $scope);
    }

    public function onExpand(array $params = []): void
    {
        $scope = $params['refine_scope'] ?? 'reel';
        $instruction = $scope === 'scene'
            ? 'Ampliar con más detalles la escena actual.'
            : 'Ampliar con más detalles todo el Reel.';

        $this->ai_instructions->value($instruction);
        $this->executeStructureReel($instruction, $scope);
    }

    public function onReduceTime(array $params = []): void
    {
        $scope = $params['refine_scope'] ?? 'reel';
        $instruction = $scope === 'scene'
            ? 'Reducir el tiempo y duración de la escena actual.'
            : 'Reducir el tiempo y duración total del Reel.';

        $this->ai_instructions->value($instruction);
        $this->executeStructureReel($instruction, $scope);
    }

    public function onIncreaseTime(array $params = []): void
    {
        $scope = $params['refine_scope'] ?? 'reel';
        $instruction = $scope === 'scene'
            ? 'Aumentar la duración de la escena actual.'
            : 'Aumentar la duración de todo el Reel.';

        $this->ai_instructions->value($instruction);
        $this->executeStructureReel($instruction, $scope);
    }

    public function onStructureReel(array $params = []): void
    {
        $rawInstructions = $params['ai_instructions'] ?? (isset($this->ai_instructions) ? $this->ai_instructions->getValue() : '');
        $instructions = is_string($rawInstructions) ? trim($rawInstructions) : '';
        $scope = $params['refine_scope'] ?? 'reel';

        $this->executeStructureReel($instructions, $scope);
    }

    protected function executeStructureReel(string $instructions, string $scope): void
    {
        $postId = $this->resolvePostId();
        if ($postId <= 0) {
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
        if ($previousJson === null && ! empty($this->state_scenes)) {
            $previousJson = json_encode([
                'metadata' => ['autor' => 'UNSJ', 'tema' => $post->title, 'duracion_total_segundos' => 30],
                'scenes' => $this->state_scenes,
            ], JSON_UNESCAPED_UNICODE);
        }

        $feedback = null;
        if ($instructions !== '') {
            $sceneNum = $this->state_current_scene_index + 1;
            $feedback = $scope === 'scene'
                ? "Ajustar únicamente la Escena {$sceneNum}: {$instructions}"
                : "Ajustar en todo el Reel: {$instructions}";
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
            Log::warning('[ReelStudio] StructureReelScriptJob failed: '.$e->getMessage());
        }

        $refreshedPost = $this->postService->findById($postId);
        $aiScript = $refreshedPost !== null ? $refreshedPost->aiScript : null;

        if ($aiScript !== null && $aiScript !== '') {
            $decoded = json_decode($aiScript, true);
            if (is_array($decoded) && ! empty($decoded['scenes'])) {
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

        $idx = $this->state_current_scene_index;
        if ($instructions !== '') {
            if (preg_match('/["\']([^"\']+)["\']/', $instructions, $matches)) {
                $newHeadline = $matches[1];
                if ($scope === 'scene' && isset($this->state_scenes[$idx])) {
                    $this->state_scenes[$idx]['text_content']['headline'] = $newHeadline;
                } else {
                    $this->state_scenes[0]['text_content']['headline'] = $newHeadline;
                }
            } elseif (str_contains(strtolower($instructions), 'resumir')) {
                if ($scope === 'scene' && isset($this->state_scenes[$idx])) {
                    $this->state_scenes[$idx]['text_content']['subheadline'] = 'Versión resumida';
                }
            } elseif (str_contains(strtolower($instructions), 'reducir')) {
                if ($scope === 'scene' && isset($this->state_scenes[$idx])) {
                    $this->state_scenes[$idx]['duration_seconds'] = max(2, ($this->state_scenes[$idx]['duration_seconds'] ?? 5) - 2);
                }
            } elseif (str_contains(strtolower($instructions), 'aumentar')) {
                if ($scope === 'scene' && isset($this->state_scenes[$idx])) {
                    $this->state_scenes[$idx]['duration_seconds'] = ($this->state_scenes[$idx]['duration_seconds'] ?? 5) + 2;
                }
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
            if (empty($this->state_scenes)) {
                $this->state_scenes = $this->getDefaultScenes();
            }

            return;
        }

        $post = $this->postService->findById($postId);
        if ($post !== null && ! empty($post->aiScript)) {
            $decoded = json_decode((string) $post->aiScript, true);
            if (is_array($decoded) && ! empty($decoded['scenes'])) {
                $this->state_scenes = $decoded['scenes'];

                return;
            }
        }

        if (empty($this->state_scenes)) {
            $this->state_scenes = $this->getDefaultScenes($post);
        }
    }

    protected function updateScenePreview(): void
    {
        if (empty($this->state_scenes)) {
            $this->state_scenes = $this->getDefaultScenes();
        }

        $totalScenes = count($this->state_scenes);
        if ($this->state_current_scene_index >= $totalScenes) {
            $this->state_current_scene_index = max(0, $totalScenes - 1);
        }
        if ($this->state_current_scene_index < 0) {
            $this->state_current_scene_index = 0;
        }

        $currentScene = $this->state_scenes[$this->state_current_scene_index] ?? null;
        $sceneNumber = $this->state_current_scene_index + 1;

        if (isset($this->scene_indicator)) {
            $this->scene_indicator->label("Escena {$sceneNumber}/{$totalScenes}");
        }

        if (isset($this->btn_prev_scene)) {
            $this->btn_prev_scene->enabled($this->state_current_scene_index > 0);
        }
        if (isset($this->btn_next_scene)) {
            $this->btn_next_scene->enabled($this->state_current_scene_index < $totalScenes - 1);
        }

        $headline = '';
        $subheadline = '';
        if ($currentScene !== null && isset($currentScene['text_content'])) {
            $headline = (string) ($currentScene['text_content']['headline'] ?? '');
            $subheadline = (string) ($currentScene['text_content']['subheadline'] ?? '');
            if ($subheadline === '' && ! empty($currentScene['text_content']['body'])) {
                $subheadline = implode(' • ', (array) $currentScene['text_content']['body']);
            }
        }

        if ($headline === '') {
            $headline = 'INNOVACIÓN EN SOFTWARE BACKEND-DRIVEN';
        }
        if ($subheadline === '') {
            $subheadline = 'PDTS Convocatoria 2025 - Universidad Nacional de San Juan';
        }

        if (isset($this->scene_headline)) {
            $this->scene_headline->text($headline);
        }
        if (isset($this->scene_subheadline)) {
            $this->scene_subheadline->text($subheadline);
        }
    }

    protected function getDefaultScenes(?PostDTO $post = null): array
    {
        $title = $post !== null && ! empty(trim($post->title))
            ? trim($post->title)
            : 'INNOVACIÓN EN SOFTWARE BACKEND-DRIVEN';

        return [
            [
                'scene_index' => 1,
                'layout_template' => 'title_hero',
                'duration_seconds' => 6,
                'text_content' => [
                    'headline' => $title,
                    'subheadline' => 'PDTS Convocatoria 2025 - Universidad Nacional de San Juan',
                    'body' => ['Beca disponible', 'Inscripción abierta'],
                ],
            ],
            [
                'scene_index' => 2,
                'layout_template' => 'bullet_points',
                'duration_seconds' => 5,
                'text_content' => [
                    'headline' => 'REQUISITOS Y ALCANCE',
                    'subheadline' => 'Estudiantes avanzados de Informática',
                    'body' => ['Dedicación 20hs', 'Plan de trabajo'],
                ],
            ],
            [
                'scene_index' => 3,
                'layout_template' => 'bullet_points',
                'duration_seconds' => 5,
                'text_content' => [
                    'headline' => 'OBJETIVOS DEL PROYECTO',
                    'subheadline' => 'Desarrollo ágil de plataformas distribuidas',
                    'body' => ['Arquitectura backend-driven', 'Transferencia'],
                ],
            ],
            [
                'scene_index' => 4,
                'layout_template' => 'bullet_points',
                'duration_seconds' => 6,
                'text_content' => [
                    'headline' => 'CRONOGRAMA Y FASES',
                    'subheadline' => 'Convocatoria Noviembre 2026',
                    'body' => ['Evaluación técnica', 'Entrevistas'],
                ],
            ],
            [
                'scene_index' => 5,
                'layout_template' => 'call_to_action',
                'duration_seconds' => 5,
                'text_content' => [
                    'headline' => 'POSTÚLATE HOY',
                    'subheadline' => 'Instituto de Informática - UNSJ',
                    'body' => ['Formulario disponible', 'Cierre próximo'],
                ],
            ],
        ];
    }
}
