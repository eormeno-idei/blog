<?php

namespace App\UI\Screens\Concerns;

use App\DTOs\ReelGenerationStatus;
use App\Jobs\GenerateReelPreviewJob;
use App\Services\Post\ReelStoryboardFormatter;
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
use Illuminate\Support\Facades\File;

trait InteractsWithReelStudio
{
    protected Timer $tmr_reel_poll;

    protected Container $workspace_tabs;

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

    protected Input $ai_response;

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

    protected function buildWorkspaceTabs(Container $formCard): Container
    {
        $this->workspace_tabs = UI::container('workspace_tabs')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->tabs([
                'form_tab' => [
                    'label' => '📝 Datos de la Publicación',
                ],
                'reel_studio_tab' => [
                    'label' => '🎬 Estudio de Reel IA',
                    'disabled' => true,
                ],
            ], 'form_tab');

        $this->workspace_tabs->add($formCard, tab: 'form_tab');
        $this->workspace_tabs->add($this->buildAiStudioCard(), tab: 'reel_studio_tab');

        return $this->workspace_tabs;
    }

    protected function enableReelStudioTab(bool $activate = false): void
    {
        if (! isset($this->workspace_tabs)) {
            return;
        }

        $this->workspace_tabs->disableTab('reel_studio_tab', false);

        if ($activate) {
            $this->workspace_tabs->activeTab('reel_studio_tab');
        }
    }

    protected function buildAiStudioCard(): Container
    {
        $this->ai_studio_card = UI::container('ai_studio_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(760))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->ai_studio_card->add($this->buildStudioHeader());
        $this->ai_studio_card->add($this->buildProgressBox());
        $this->ai_studio_card->add($this->buildPreviewBox());

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
                ->text('🎬 Estudio de Reel IA')
                ->style('h3')
        );

        $this->lbl_version_badge = UI::label('lbl_version_badge')
            ->text('Sin previsualización')
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
            ->text('💡 Pulsa "✨ Generar Reel con IA" para crear una previsualización animada a partir de tus notas.')
            ->style('info')
            ->bold();

        $this->lbl_ai_progress_detail = UI::label('lbl_ai_progress_detail')
            ->text('Podrás revisar el video escena por escena y pedirle ajustes a la IA tantas veces como necesites antes de enviarlo a revisión.')
            ->style('muted')
            ->size('small');

        $this->ai_progress_box->add($this->lbl_ai_progress_step);
        $this->ai_progress_box->add($this->lbl_ai_progress_detail);

        return $this->ai_progress_box;
    }

    private function buildPreviewBox(): Container
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
                ->text('📋 Resumen de Escenas')
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
                ->text('🔄 ¿No estás conforme? Pide un ajuste')
                ->style('h4')
        );

        $refinementCard->add($this->buildQuickChipsRow());

        $this->ai_feedback = UI::textarea('ai_feedback')
            ->label('Instrucciones de modificación para la IA')
            ->placeholder('Ej: En la escena 2 agrega un punto sobre becas, cambia el título final o haz que dure 30 segundos...')
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(90))
            ->maxLength(600);

        $this->btn_refine_ai = UI::button('btn_refine_ai')
            ->label('✨ Aplicar Ajustes y Re-renderizar')
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
                ->label('⚡ Más corto (máx 30s)')
                ->action('quick_refine', ['prompt' => 'Reduce la duración total a un máximo de 30 segundos resumiendo las escenas.'])
                ->style('secondary')
                ->size('small')
        );

        $quickChipsRow->add(
            UI::button('btn_chip_dynamic')
                ->label('🎨 Más dinámico')
                ->action('quick_refine', ['prompt' => 'Usa animaciones más dinámicas, alterna entradas slide_from_left/right y reduce el tiempo por escena.'])
                ->style('secondary')
                ->size('small')
        );

        $quickChipsRow->add(
            UI::button('btn_chip_impact')
                ->label('🎯 Títulos más impactantes')
                ->action('quick_refine', ['prompt' => 'Reescribe los titulares (headline) de cada escena para que sean más directos, atractivos e impactantes.'])
                ->style('secondary')
                ->size('small')
        );

        return $quickChipsRow;
    }

    /**
     * Restaura el estado del Estudio de Reel desde el registro en base de datos,
     * incluyendo versiones previas o un procesamiento de IA en curso si el usuario cerró la ventana y volvió a entrar.
     */
    protected function restoreReelStudioFromPost(object $post): void
    {
        $status = isset($post->id) && is_numeric($post->id)
            ? GenerateReelPreviewJob::getStatus((int) $post->id)
            : null;

        $savedVersions = $status !== null ? $status->versions : [];
        if ($savedVersions !== []) {
            $this->hydrateSavedVersions($savedVersions);
        } else {
            $this->preloadExistingReel(
                is_string($post->ai_script ?? null) ? $post->ai_script : null,
                is_string($post->reel_video ?? null) ? $post->reel_video : null
            );
        }

        $jobStatus = $status !== null ? $status->status : (is_string($post->ai_job_status ?? null) ? $post->ai_job_status : 'idle');
        if (in_array($jobStatus, ['generating_script', 'rendering_video'], true)) {
            $this->state_active_job_id = (string) ($post->id ?? '');
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
            $this->enableReelStudioTab(activate: false);
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
        $this->enableReelStudioTab(activate: false);
        $this->applyCurrentVersionToUI();
    }

    /**
     * Precarga una versión existente (por ejemplo, al abrir un post que ya tenía ai_script o reel_video).
     */
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

    /**
     * @param  array<string, mixed>  $params
     */
    public function onAskAI(array $params): void
    {
        $rawNotes = $params['post_content'] ?? $this->post_content->getValue();
        $notes = is_string($rawNotes) ? trim($rawNotes) : '';
        if ($notes === '') {
            $this->toast('Escribe las notas del post antes de generar el Reel con IA.', 'warning');

            return;
        }

        $this->post_content->value($notes);
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
            $this->toast('Por favor escribe qué cambios deseas aplicar al video.', 'warning');

            return;
        }

        $rawNotes = $params['post_content'] ?? $this->post_content->getValue();
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
        $rawNotes = $params['post_content'] ?? $this->post_content->getValue();
        $notes = is_string($rawNotes) ? trim($rawNotes) : '';
        $previousJson = $this->resolveCurrentJsonScript();

        $this->startReelGenerationJob($notes, $previousJson, $presetPrompt);
    }

    private function resolveCurrentJsonScript(): ?string
    {
        $currentVersion = $this->getCurrentVersionData();
        $rawScript = $currentVersion['json_script'] ?? $this->ai_response->get('value') ?? '';
        $script = is_string($rawScript) ? trim($rawScript) : '';

        return $script !== '' ? $script : null;
    }

    protected function startReelGenerationJob(string $notes, ?string $previousJson, ?string $userFeedback): void
    {
        if ($this->state_active_job_id !== '') {
            $this->toast('Ya hay una generación de video en curso. Espera unos segundos.', 'info');

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
        $this->enableReelStudioTab(activate: true);

        $this->ai_progress_box->visible(true);
        $this->lbl_ai_progress_step
            ->text($isRefinement
                ? "🧠 Paso 1/2: Ajustando el guion para la Versión {$versionNumber}..."
                : '🧠 Paso 1/2: Analizando tus notas y estructurando el guion con IA...')
            ->style('warning');

        $this->lbl_ai_progress_detail
            ->text($isRefinement
                ? "Aplicando cambio: \"{$userFeedback}\""
                : 'Diseñando titulares, viñetas y tiempos de animación...');

        $this->lbl_version_badge
            ->text("Generando v{$versionNumber}...")
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
            ->text('🧠 '.($status->message ?? 'Paso 1/2: Generando guion con IA...'))
            ->style('warning');
        $this->lbl_ai_progress_detail
            ->text($status->detail ?? '');
    }

    private function handleRenderingVideoStatus(ReelGenerationStatus $status): void
    {
        $this->lbl_ai_progress_step
            ->text('🎬 '.($status->message ?? 'Paso 2/2: Renderizando video con Remotion...'))
            ->style('info');
        $this->lbl_ai_progress_detail
            ->text($status->detail ?? 'Codificando fotogramas en MP4...');

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
        $this->btn_refine_ai->loading(false)->label('✨ Aplicar Ajustes y Re-renderizar')->enabled(true);
        $this->ai_feedback->value('');

        $this->applyCurrentVersionToUI();
        $this->toast("¡Previsualización v{$versionNumber} lista!", 'success');
    }

    private function handleFailedStatus(ReelGenerationStatus $status): void
    {
        $this->tmr_reel_poll->stop();
        $this->state_active_job_id = '';

        $this->lbl_ai_progress_step
            ->text('❌ '.($status->message ?? 'Error al generar el video.'))
            ->style('danger');
        $this->lbl_ai_progress_detail
            ->text($status->detail ?? 'Intenta nuevamente o ajusta tus notas.');

        $this->lbl_version_badge->text('Error en generación')->style('danger');
        $this->btn_ask_ai->loading(false)->label('✨ Reintentar con IA')->enabled(true);
        $this->btn_refine_ai->loading(false)->label('✨ Aplicar Ajustes y Re-renderizar')->enabled(true);

        $this->toast('Ocurrió un error al generar el video con IA.', 'error');
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
            ->text("✅ Previsualización v{$versionNum} lista ({$durationSec}s en total)")
            ->style('success');

        $this->lbl_ai_progress_detail
            ->text('Revisa el video y el resumen de escenas. Si estás conforme, envíalo a revisión.');

        $this->ai_preview_box->visible(true);
        $this->btn_prev_version->enabled($this->state_current_version_index > 0);
        $this->btn_next_version->enabled($this->state_current_version_index < $totalVersions - 1);

        if (isset($this->btn_save_post)) {
            $this->btn_save_post->enabled(true);
        }

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

        if (isset($this->postService) && $this->state_post_id > 0) {
            $rawVideoFilename = $versionData['video_filename'] ?? null;
            $videoFilename = is_string($rawVideoFilename) ? $rawVideoFilename : '';
            $this->postService->updatePost($this->state_post_id, [
                'ai_script' => $jsonScript !== '' ? $jsonScript : null,
                'reel_video' => $videoFilename !== '' ? $videoFilename : null,
            ]);
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

    /**
     * Elimina del disco los archivos MP4 de las versiones descartadas durante la sesión.
     */
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
        if ($this->state_active_job_id !== '') {
            $this->tmr_reel_poll->stop();
            $this->state_active_job_id = '';
        }
    }
}
