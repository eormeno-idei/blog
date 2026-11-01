<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\DTOs\ReelGenerationStatus;
use App\Jobs\GenerateReelPreviewJob;
use App\Services\Post\ReelStoryboardFormatter;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Timer;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * Step 3: Estructurar Reel con IA (Interacción exclusiva con LLM, simulación visual y refinamiento).
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 * @property Textarea $post_content
 * @property Button $btn_save_post
 * @property \Idei\Usim\Components\Textarea $post_content
 * @property \Idei\Usim\Components\Button $btn_save_post
 *
 * @method void goToStep(string $tabId)
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method static toast(string $message, string $type = 'info')
 */
trait HasAiReelStructureStep
{
    protected Timer $tmr_reel_poll;

    protected Container $ai_studio_card;

    protected Container $ai_progress_box;

    protected Label $lbl_ai_progress_step;

    protected Label $lbl_ai_progress_detail;

    protected Container $ai_preview_box;

    protected Label $lbl_version_badge;

    protected Button $btn_prev_version;

    protected Button $btn_next_version;

    protected Carousel $reel_preview_player;

    protected Label $lbl_storyboard_summary;

    protected Textarea $ai_feedback;

    protected Button $btn_refine_ai;

    protected Button $btn_ask_ai;

    protected Input $ai_response;

    protected Button $btn_step3_back;

    protected Button $btn_step3_next;

    protected string $state_active_job_id = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $state_reel_versions = [];

    protected int $state_current_version_index = -1;

    protected function buildReelPollTimer(): Timer
    {
        $this->tmr_reel_poll = UI::timer('tmr_reel_poll')
            ->action('check_reel_status')
            ->every(2500)
            ->enabled(false);

        return $this->tmr_reel_poll;
    }

    protected function buildAiReelStructureStepCard(?PostDTO $post = null): Container
    {
        $this->ai_studio_card = UI::container('ai_studio_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(760))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->ai_response = UI::input('ai_response')
            ->type('hidden')
            ->value($post !== null ? (string) ($post->aiScript ?? '') : '');

        $this->ai_studio_card->add($this->ai_response);
        $this->ai_studio_card->add($this->buildStudioHeader());
        $this->ai_studio_card->add($this->buildProgressBox());
        $this->ai_studio_card->add($this->buildPreviewAndSimulationBox());
        $this->ai_studio_card->add($this->buildStep3ActionsRow());

        return $this->ai_studio_card;
    }

    private function buildStudioHeader(): Container
    {
        $studioHeader = UI::container('ai_studio_header')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::zero());

        $studioHeader->add(
            UI::label('lbl_studio_title')
                ->text('🎬 Estructuración de Reel con IA')
                ->style('h3')
        );

        $this->lbl_version_badge = UI::label('lbl_version_badge')
            ->text('Sin estructuración')
            ->variant('badge')
            ->secondary();

        $studioHeader->add($this->lbl_version_badge);

        return $studioHeader;
    }

    private function buildProgressBox(): Container
    {
        $this->ai_progress_box = UI::container('ai_progress_box')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(8));

        $this->lbl_ai_progress_step = UI::label('lbl_ai_progress_step')
            ->text('💡 Pulsa "✨ Generar Reel con IA" para que el LLM estructure el guion a partir de tus notas.')
            ->style('info')
            ->bold();

        $this->lbl_ai_progress_detail = UI::label('lbl_ai_progress_detail')
            ->text('Podrás simular la visualización escena por escena y ajustar el guion antes de enviarlo a revisión.')
            ->style('muted')
            ->size('small');

        $this->ai_progress_box->add($this->lbl_ai_progress_step);
        $this->ai_progress_box->add($this->lbl_ai_progress_detail);

        return $this->ai_progress_box;
    }

    private function buildPreviewAndSimulationBox(): Container
    {
        $this->ai_preview_box = UI::container('ai_preview_box')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(16))
            ->visible(false);

        $this->ai_preview_box->add($this->buildVersionNavRow());

        // Simulador visual de escenas y reproductor de previsualización (9:16 vertical)
        $this->reel_preview_player = UI::carousel('reel_preview_player')
            ->modeManual()
            ->loop(true)
            ->showPrev(false)
            ->showNext(false)
            ->hideIndicators()
            ->width(Size::px(315))
            ->maxWidth(Size::px(315))
            ->height(Size::px(560))
            ->aspect(AspectRatio::_9_16)
            ->objectFit('contain');

        $this->ai_preview_box->add($this->reel_preview_player);
        $this->ai_preview_box->add($this->buildStoryboardCard());
        $this->ai_preview_box->add($this->buildRefinementCard());

        return $this->ai_preview_box;
    }

    private function buildVersionNavRow(): Container
    {
        $versionNavRow = UI::container('ai_version_nav_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(8));

        $this->btn_prev_version = UI::button('btn_prev_version')
            ->label('← Versión anterior')
            ->action('select_prev_version')
            ->style('secondary')
            ->size('small')
            ->enabled(false);

        $this->btn_next_version = UI::button('btn_next_version')
            ->label('Versión siguiente →')
            ->action('select_next_version')
            ->style('secondary')
            ->size('small')
            ->enabled(false);

        $versionNavRow->add($this->btn_prev_version);
        $versionNavRow->add($this->btn_next_version);

        return $versionNavRow;
    }

    private function buildStoryboardCard(): Container
    {
        $storyboardCard = UI::container('ai_storyboard_card')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(14))
            ->gap(Spacing::px(6));

        $storyboardCard->add(
            UI::label('lbl_storyboard_header')
                ->text('📋 Simulación y Resumen de Escenas')
                ->style('primary')
                ->bold()
                ->size('small')
        );

        $this->lbl_storyboard_summary = UI::label('lbl_storyboard_summary')
            ->text('')
            ->markdown(true)
            ->size('small');

        $storyboardCard->add($this->lbl_storyboard_summary);

        return $storyboardCard;
    }

    private function buildRefinementCard(): Container
    {
        $refinementCard = UI::container('ai_refinement_card')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(12));

        $refinementCard->add(
            UI::label('lbl_refinement_title')
                ->text('🔄 ¿Deseas perfeccionar el guion? Pide un ajuste a la IA')
                ->style('h4')
        );

        $refinementCard->add($this->buildQuickChipsRow());

        $this->ai_feedback = UI::textarea('ai_feedback')
            ->label('Instrucciones de refinamiento para el LLM')
            ->placeholder('Ej: Haz que la escena 2 enfatice las fechas límite, cambia el título o reduce la duración a 20s...')
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(90))
            ->maxLength(600);

        $this->btn_refine_ai = UI::button('btn_refine_ai')
            ->label('✨ Reestructurar Guion con Ajustes')
            ->action('refine_ai')
            ->style('primary')
            ->fullWidth(true);

        $refinementCard->add($this->ai_feedback);
        $refinementCard->add($this->btn_refine_ai);

        return $refinementCard;
    }

    private function buildQuickChipsRow(): Container
    {
        $quickChipsRow = UI::container('ai_quick_chips_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(8));

        $quickChipsRow->add(
            UI::button('btn_chip_shorter')
                ->label('⚡ Más corto (máx 25s)')
                ->action('quick_refine', ['prompt' => 'Reduce la duración total a un máximo de 25 segundos resumiendo las escenas.'])
                ->style('secondary')
                ->size('small')
        );

        $quickChipsRow->add(
            UI::button('btn_chip_dynamic')
                ->label('🎨 Más dinámico')
                ->action('quick_refine', ['prompt' => 'Usa animaciones más dinámicas, alterna entradas y reduce el tiempo por escena.'])
                ->style('secondary')
                ->size('small')
        );

        $quickChipsRow->add(
            UI::button('btn_chip_impact')
                ->label('🎯 Titulares impactantes')
                ->action('quick_refine', ['prompt' => 'Reescribe los titulares (headline) de cada escena para que sean más directos y memorables.'])
                ->style('secondary')
                ->size('small')
        );

        return $quickChipsRow;
    }

    private function buildStep3ActionsRow(): Container
    {
        $actions = UI::container('step3_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(12));

        $this->btn_step3_back = UI::button('btn_step3_back')
            ->label('← Atrás: Imagen')
            ->action('step3_back')
            ->style('secondary');

        $this->btn_ask_ai = UI::button('btn_ask_ai')
            ->label('✨ Generar Reel con IA')
            ->action('ask_ai')
            ->style('info');

        $this->btn_step3_next = UI::button('btn_step3_next')
            ->label('Siguiente: Enviar a Revisión →')
            ->action('step3_next')
            ->style('primary')
            ->enabled(false);

        $actions->add($this->btn_step3_back);
        $actions->add($this->btn_ask_ai);
        $actions->add($this->btn_step3_next);

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep3Back(array $params): void
    {
        $this->goToStep('step_image_tab');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep3Next(array $params): void
    {
        $this->enableStepTab('step_review_submission_tab', activate: true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onAskAI(array $params): void
    {
        $rawNotes = $params['post_content'] ?? (isset($this->post_content) ? $this->post_content->getValue() : '');
        $notes = is_string($rawNotes) ? trim($rawNotes) : '';
        if ($notes === '') {
            $this->toast('Escribe las notas del post antes de estructurar el Reel con IA.', 'warning');

            return;
        }

        if (isset($this->post_content)) {
            $this->post_content->value($notes);
        }

        $this->startReelGenerationJob($notes, null, null);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onRefineAi(array $params): void
    {
        $rawFeedback = $params['ai_feedback'] ?? $this->ai_feedback->getValue();
        $feedback = is_string($rawFeedback) ? trim($rawFeedback) : '';
        if ($feedback === '') {
            $this->toast('Por favor escribe qué cambios deseas aplicar al guion.', 'warning');

            return;
        }

        $rawNotes = $params['post_content'] ?? (isset($this->post_content) ? $this->post_content->getValue() : '');
        $notes = is_string($rawNotes) ? trim($rawNotes) : '';
        $previousJson = $this->resolveCurrentJsonScript();

        $this->startReelGenerationJob($notes, $previousJson, $feedback);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onQuickRefine(array $params): void
    {
        $rawPrompt = $params['prompt'] ?? '';
        $presetPrompt = is_string($rawPrompt) ? trim($rawPrompt) : '';
        if ($presetPrompt === '') {
            return;
        }

        $this->ai_feedback->value($presetPrompt);
        $rawNotes = $params['post_content'] ?? (isset($this->post_content) ? $this->post_content->getValue() : '');
        $notes = is_string($rawNotes) ? trim($rawNotes) : '';
        $previousJson = $this->resolveCurrentJsonScript();

        $this->startReelGenerationJob($notes, $previousJson, $presetPrompt);
    }

    private function resolveCurrentJsonScript(): ?string
    {
        $currentVersion = $this->getCurrentVersionData();
        $rawScript = $currentVersion['json_script'] ?? (isset($this->ai_response) ? $this->ai_response->get('value') : null) ?? '';
        $script = is_string($rawScript) ? trim($rawScript) : '';

        return $script !== '' ? $script : null;
    }

    protected function startReelGenerationJob(string $notes, ?string $previousJson, ?string $userFeedback): void
    {
        if ($this->state_active_job_id !== '') {
            $this->toast('Ya hay un procesamiento de IA en curso. Espera unos segundos.', 'info');

            return;
        }

        if ($this->state_post_id <= 0) {
            return;
        }

        $postId = (int) $this->state_post_id;
        $nextVersionNumber = count($this->state_reel_versions) + 1;
        $this->state_active_job_id = (string) $postId;
        $isRefinement = $previousJson !== null && $userFeedback !== null;

        GenerateReelPreviewJob::putStatus(
            $postId,
            ReelGenerationStatus::generatingScript($nextVersionNumber, $isRefinement, $userFeedback),
        );

        GenerateReelPreviewJob::dispatch($postId, $notes, $previousJson, $userFeedback, $nextVersionNumber);

        $this->updateUiForJobStart($nextVersionNumber, $isRefinement, $userFeedback);
    }

    private function updateUiForJobStart(int $versionNumber, bool $isRefinement, ?string $userFeedback): void
    {
        $this->enableStepTab('reel_studio_tab', activate: true);

        $this->ai_progress_box->visible(true);
        $this->lbl_ai_progress_step
            ->text($isRefinement
                ? "🧠 Ajustando el guion para la Versión {$versionNumber}..."
                : '🧠 Analizando tus notas y estructurando el guion con IA...')
            ->style('warning');

        $this->lbl_ai_progress_detail
            ->text($isRefinement
                ? "Aplicando cambio: \"{$userFeedback}\""
                : 'Diseñando titulares, viñetas y tiempos de animación...');

        $this->lbl_version_badge
            ->text("Estructurando v{$versionNumber}...")
            ->style('warning');

        $this->btn_ask_ai->loading(true, 'Generando...')->enabled(false);
        $this->btn_refine_ai->loading(true, 'Aplicando ajustes...')->enabled(false);
        $this->tmr_reel_poll->start();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCheckReelStatus(array $params): void
    {
        if ($this->state_active_job_id === '') {
            $this->tmr_reel_poll->stop();

            return;
        }

        $postId = (int) $this->state_active_job_id;
        $status = GenerateReelPreviewJob::getStatus($postId);
        if ($status === null) {
            return;
        }

        match ($status->status) {
            'generating_script' => $this->handleGeneratingScriptStatus($status),
            'rendering_video' => $this->handleRenderingVideoStatus($status),
            'completed' => $this->handleCompletedStatus($status),
            'failed' => $this->handleFailedStatus($status),
            default => null,
        };
    }

    private function handleGeneratingScriptStatus(ReelGenerationStatus $status): void
    {
        $this->lbl_ai_progress_step
            ->text('🧠 '.($status->message ?? 'Estructurando guion con IA...'))
            ->style('warning');
        $this->lbl_ai_progress_detail
            ->text($status->detail ?? '');
    }

    private function handleRenderingVideoStatus(ReelGenerationStatus $status): void
    {
        $this->lbl_ai_progress_step
            ->text('🎬 '.($status->message ?? 'Renderizando previsualización con Remotion...'))
            ->style('info');
        $this->lbl_ai_progress_detail
            ->text($status->detail ?? 'Codificando fotogramas...');

        if ($status->parsedScript !== null) {
            $storyboard = app(ReelStoryboardFormatter::class)->format($status->parsedScript);
            $this->lbl_storyboard_summary->text($storyboard);
        }
    }

    private function handleCompletedStatus(ReelGenerationStatus $status): void
    {
        $this->tmr_reel_poll->stop();
        $this->state_active_job_id = '';

        $versionNumber = $status->version ?? (count($this->state_reel_versions) + 1);
        $jsonScript = $status->jsonScript ?? '';
        $feedbackApplied = $status->feedbackApplied;

        $storyboardMarkdown = app(ReelStoryboardFormatter::class)->format(
            $status->parsedScript ?? $jsonScript,
            $feedbackApplied
        );

        $versionEntry = $status->toVersionEntry($versionNumber);
        $versionEntry['storyboard_markdown'] = $storyboardMarkdown;

        $this->state_reel_versions[] = $versionEntry;
        $this->state_current_version_index = count($this->state_reel_versions) - 1;

        $this->btn_ask_ai->loading(false)->label('🔄 Regenerar desde Notas')->enabled(true);
        $this->btn_refine_ai->loading(false)->label('✨ Aplicar Ajustes')->enabled(true);
        $this->ai_feedback->value('');

        $this->applyCurrentVersionToUI();
        $this->toast("¡Estructura de Reel v{$versionNumber} lista!", 'success');
    }

    private function handleFailedStatus(ReelGenerationStatus $status): void
    {
        $this->tmr_reel_poll->stop();
        $this->state_active_job_id = '';

        $this->lbl_ai_progress_step
            ->text('❌ '.($status->message ?? 'Error al procesar el guion.'))
            ->style('danger');
        $this->lbl_ai_progress_detail
            ->text($status->detail ?? 'Intenta nuevamente o revisa tus notas.');

        $this->lbl_version_badge->text('Error')->style('danger');
        $this->btn_ask_ai->loading(false)->label('✨ Reintentar con IA')->enabled(true);
        $this->btn_refine_ai->loading(false)->label('✨ Aplicar Ajustes')->enabled(true);

        $this->toast('Ocurrió un error al procesar el guion con IA.', 'error');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSelectPrevVersion(array $params): void
    {
        if ($this->state_current_version_index > 0) {
            $this->state_current_version_index--;
            $this->applyCurrentVersionToUI();
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSelectNextVersion(array $params): void
    {
        if ($this->state_current_version_index < count($this->state_reel_versions) - 1) {
            $this->state_current_version_index++;
            $this->applyCurrentVersionToUI();
        }
    }

    protected function applyCurrentVersionToUI(): void
    {
        $versionData = $this->getCurrentVersionData();
        if ($versionData === null) {
            return;
        }

        $totalVersions = count($this->state_reel_versions);
        $rawVersion = $versionData['version'] ?? null;
        $versionNum = is_numeric($rawVersion) ? (int) $rawVersion : ($this->state_current_version_index + 1);
        $rawScenes = $versionData['scene_count'] ?? null;
        $sceneCount = is_numeric($rawScenes) ? (int) $rawScenes : 0;
        $rawDuration = $versionData['duration_seconds'] ?? null;
        $durationSec = is_numeric($rawDuration) ? (int) $rawDuration : 0;
        $rawVideoUrl = $versionData['video_url'] ?? null;
        $videoUrl = is_string($rawVideoUrl) ? $rawVideoUrl : '';
        $rawJsonScript = $versionData['json_script'] ?? null;
        $jsonScript = is_string($rawJsonScript) ? $rawJsonScript : '';
        $rawStoryboard = $versionData['storyboard_markdown'] ?? null;
        $storyboard = is_string($rawStoryboard) ? $rawStoryboard : '';

        $this->ai_response->value($jsonScript);

        $this->lbl_version_badge
            ->text("Versión {$versionNum} de {$totalVersions} • {$sceneCount} escenas ({$durationSec}s)")
            ->style('success');

        $this->lbl_ai_progress_step
            ->text("✅ Estructura v{$versionNum} lista ({$durationSec}s en total)")
            ->style('success');

        $this->lbl_ai_progress_detail
            ->text('Revisa el resumen de escenas y la simulación. Si estás conforme, continúa a revisión.');

        $this->ai_preview_box->visible(true);
        $this->btn_prev_version->enabled($this->state_current_version_index > 0);
        $this->btn_next_version->enabled($this->state_current_version_index < $totalVersions - 1);
        $this->btn_step3_next->enabled(true);

        if (isset($this->btn_save_post)) {
            $this->btn_save_post->enabled(true);
        }

        // Simulación visual en el reproductor (si hay video cargado, o placeholder simulado)
        if ($videoUrl !== '') {
            $this->reel_preview_player->currentMedia([
                'id' => "reel_v{$versionNum}",
                'kind' => 'video',
                'url' => $videoUrl,
                'mime' => 'video/mp4',
                'title' => "Previsualización Versión {$versionNum}",
                'duration_ms' => max(1000, $durationSec * 1000),
            ]);
        }

        $this->lbl_storyboard_summary->text($storyboard);

        if ($this->state_post_id > 0) {
            $existingPost = $this->postService->findById($this->state_post_id);
            $statusVal = $existingPost !== null ? (is_string($existingPost->status) ? $existingPost->status : $existingPost->status->value) : '';
            if ($existingPost !== null && ! in_array($statusVal, ['published', 'archived'], true)) {
                $rawVideoFilename = $versionData['video_filename'] ?? null;
                $videoFilename = is_string($rawVideoFilename) ? $rawVideoFilename : '';
                $this->postService->updatePost($this->state_post_id, [
                    'ai_script' => $jsonScript !== '' ? $jsonScript : null,
                    'reel_video' => $videoFilename !== '' ? $videoFilename : null,
                ], Auth::user());
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getCurrentVersionData(): ?array
    {
        if (
            $this->state_current_version_index < 0
            || ! isset($this->state_reel_versions[$this->state_current_version_index])
        ) {
            return null;
        }

        return $this->state_reel_versions[$this->state_current_version_index];
    }

    protected function restoreReelStudioFromPost(?PostDTO $post = null): void
    {
        if ($post === null) {
            return;
        }

        $status = GenerateReelPreviewJob::getStatus((int) $post->id);

        $savedVersions = $status !== null ? $status->versions : [];
        if ($savedVersions !== []) {
            $this->hydrateSavedVersions($savedVersions);
        } else {
            $this->preloadExistingReel(
                $post->aiScript,
                $post->reelVideo
            );
        }

        $jobStatus = $status !== null ? $status->status : $post->aiJobStatus;
        if (in_array($jobStatus, ['generating_script', 'rendering_video'], true)) {
            $this->state_active_job_id = (string) $post->id;
            $versionNumber = $status !== null && $status->version !== null ? $status->version : (count($this->state_reel_versions) + 1);
            $this->updateUiForJobStart($versionNumber, false, null);

            if ($status !== null) {
                if ($jobStatus === 'rendering_video') {
                    $this->handleRenderingVideoStatus($status);
                } else {
                    $this->handleGeneratingScriptStatus($status);
                }
            }

            return;
        }

        if ($jobStatus === 'failed' && $status !== null) {
            $this->enableStepTab('reel_studio_tab', activate: false);
            $this->handleFailedStatus($status);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $savedVersions
     */
    private function hydrateSavedVersions(array $savedVersions): void
    {
        $formatter = app(ReelStoryboardFormatter::class);
        $this->state_reel_versions = [];

        foreach ($savedVersions as $idx => $ver) {
            $jsonScript = isset($ver['json_script']) && is_string($ver['json_script']) ? $ver['json_script'] : '';
            $feedback = isset($ver['feedback_applied']) && is_string($ver['feedback_applied'])
                ? $ver['feedback_applied']
                : null;
            $verNum = isset($ver['version']) && is_numeric($ver['version']) ? (int) $ver['version'] : ($idx + 1);
            $videoUrl = isset($ver['video_url']) && is_string($ver['video_url']) ? $ver['video_url'] : '';
            $videoFile = isset($ver['video_filename']) && is_string($ver['video_filename']) ? $ver['video_filename'] : '';
            $scenes = isset($ver['scene_count']) && is_numeric($ver['scene_count']) ? (int) $ver['scene_count'] : 0;
            $duration = isset($ver['duration_seconds']) && is_numeric($ver['duration_seconds']) ? (int) $ver['duration_seconds'] : 10;

            $this->state_reel_versions[] = [
                'version' => $verNum,
                'video_url' => $videoUrl,
                'video_filename' => $videoFile,
                'json_script' => $jsonScript,
                'scene_count' => $scenes,
                'duration_seconds' => $duration,
                'storyboard_markdown' => $formatter->format($jsonScript, $feedback),
                'feedback_applied' => $feedback,
            ];
        }

        $this->state_current_version_index = count($this->state_reel_versions) - 1;
        $this->enableStepTab('reel_studio_tab', activate: false);
        $this->applyCurrentVersionToUI();
    }

    protected function preloadExistingReel(?string $jsonScript, ?string $videoFilename): void
    {
        $script = trim((string) $jsonScript);
        $video = trim((string) $videoFilename);

        if ($script === '' && $video === '') {
            return;
        }

        $rawDecoded = $script !== '' ? json_decode($script, true) : [];
        $decoded = is_array($rawDecoded) ? $rawDecoded : [];
        $scenes = is_array($decoded['scenes'] ?? null) ? $decoded['scenes'] : [];

        $this->hydrateSavedVersions([[
            'version' => 1,
            'video_url' => $video !== '' ? "/storage/reels/{$video}" : '',
            'video_filename' => $video,
            'json_script' => $script,
            'scene_count' => count($scenes),
            'duration_seconds' => 10,
            'feedback_applied' => null,
        ]]);
    }

    protected function cleanupDiscardedReelFiles(?string $keptVideoFilename): void
    {
        foreach ($this->state_reel_versions as $version) {
            $rawFilename = $version['video_filename'] ?? null;
            $filename = is_string($rawFilename) ? $rawFilename : '';
            if ($filename === '' || $filename === $keptVideoFilename) {
                continue;
            }

            $filePath = storage_path("app/public/reels/{$filename}");
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }
    }

    protected function stopActiveReelJobPolling(): void
    {
        if ($this->state_active_job_id !== '' && isset($this->tmr_reel_poll)) {
            $this->tmr_reel_poll->stop();
            $this->state_active_job_id = '';
        }
    }
}
