<?php

namespace App\UI\Screens\Concerns;

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
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait InteractsWithReelStudio
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

    protected function buildAiStudioCard(): Container
    {
        $this->ai_studio_card = UI::container('ai_studio_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(540))
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
            ->style('secondary');

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
            ->hideIndicators();

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
     * Precarga una versión existente (por ejemplo, al abrir EditPost para corregir observaciones del revisor).
     */
    protected function preloadExistingReel(?string $jsonScript, ?string $videoFilename): void
    {
        $hasScript = is_string($jsonScript) && trim($jsonScript) !== '';
        $hasVideo = is_string($videoFilename) && trim($videoFilename) !== '';

        if (! $hasScript && ! $hasVideo) {
            return;
        }

        $formatter = app(ReelStoryboardFormatter::class);
        $decoded = $hasScript ? (json_decode($jsonScript, true) ?: []) : [];
        $scenes = is_array($decoded['scenes'] ?? null) ? $decoded['scenes'] : [];

        $durationSeconds = 0;
        foreach ($scenes as $scene) {
            $durationSeconds += (int) ($scene['duration_seconds'] ?? 0);
        }

        $this->state_reel_versions = [[
            'version' => 1,
            'video_url' => $hasVideo ? "/storage/reels/{$videoFilename}" : '',
            'video_filename' => $hasVideo ? $videoFilename : '',
            'json_script' => $hasScript ? $jsonScript : '',
            'scene_count' => count($scenes),
            'duration_seconds' => max(10, $durationSeconds),
            'storyboard_markdown' => $formatter->format($decoded),
            'feedback_applied' => null,
        ]];

        $this->state_current_version_index = 0;
        $this->applyCurrentVersionToUI();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onAskAI(array $params): void
    {
        $notes = trim((string) ($params['post_content'] ?? $this->post_content->getValue()));
        if ($notes === '') {
            $this->toast('Escribe las notas del post antes de generar el Reel con IA.', 'warning');

            return;
        }

        $this->post_content->value($notes);
        $this->startReelGenerationJob($notes, null, null);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onRefineAi(array $params): void
    {
        $feedback = trim((string) ($params['ai_feedback'] ?? $this->ai_feedback->getValue()));
        if ($feedback === '') {
            $this->toast('Por favor escribe qué cambios deseas aplicar al video.', 'warning');

            return;
        }

        $notes = trim((string) ($params['post_content'] ?? $this->post_content->getValue()));
        $previousJson = $this->resolveCurrentJsonScript();

        $this->startReelGenerationJob($notes, $previousJson, $feedback);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onQuickRefine(array $params): void
    {
        $presetPrompt = trim((string) ($params['prompt'] ?? ''));
        if ($presetPrompt === '') {
            return;
        }

        $this->ai_feedback->value($presetPrompt);
        $notes = trim((string) ($params['post_content'] ?? $this->post_content->getValue()));
        $previousJson = $this->resolveCurrentJsonScript();

        $this->startReelGenerationJob($notes, $previousJson, $presetPrompt);
    }

    private function resolveCurrentJsonScript(): ?string
    {
        $currentVersion = $this->getCurrentVersionData();
        $script = (string) ($currentVersion['json_script'] ?? $this->ai_response->get('value') ?? '');

        return trim($script) !== '' ? $script : null;
    }

    protected function startReelGenerationJob(string $notes, ?string $previousJson, ?string $userFeedback): void
    {
        if ($this->state_active_job_id !== '') {
            $this->toast('Ya hay una generación de video en curso. Espera unos segundos.', 'info');

            return;
        }

        $jobId = Str::uuid()->toString();
        $nextVersionNumber = count($this->state_reel_versions) + 1;
        $this->state_active_job_id = $jobId;
        $isRefinement = $previousJson !== null && $userFeedback !== null;

        GenerateReelPreviewJob::putStatus($jobId, [
            'status' => 'generating_script',
            'step' => 1,
            'total_steps' => 2,
            'version' => $nextVersionNumber,
            'message' => $isRefinement
                ? "Paso 1/2: Ajustando el guion (Versión {$nextVersionNumber}) según tus indicaciones..."
                : 'Paso 1/2: Analizando tus notas y creando el guion con IA...',
            'detail' => $isRefinement
                ? "Modificación solicitada: \"{$userFeedback}\""
                : 'Conectando con el agente de IA para diseñar las escenas del Reel.',
        ]);

        GenerateReelPreviewJob::dispatch($jobId, $notes, $previousJson, $userFeedback, $nextVersionNumber);

        $this->updateUiForJobStart($nextVersionNumber, $isRefinement, $userFeedback);
    }

    private function updateUiForJobStart(int $versionNumber, bool $isRefinement, ?string $userFeedback): void
    {
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
     * @param array<string, mixed> $params
     */
    public function onCheckReelStatus(array $params): void
    {
        if ($this->state_active_job_id === '') {
            $this->tmr_reel_poll->stop();

            return;
        }

        $statusData = GenerateReelPreviewJob::getStatus($this->state_active_job_id);
        if ($statusData === null) {
            return;
        }

        $status = (string) ($statusData['status'] ?? '');

        match ($status) {
            'generating_script' => $this->handleGeneratingScriptStatus($statusData),
            'rendering_video' => $this->handleRenderingVideoStatus($statusData),
            'completed' => $this->handleCompletedStatus($statusData),
            'failed' => $this->handleFailedStatus($statusData),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $statusData
     */
    private function handleGeneratingScriptStatus(array $statusData): void
    {
        $this->lbl_ai_progress_step
            ->text('🧠 ' . (string) ($statusData['message'] ?? 'Paso 1/2: Generando guion con IA...'))
            ->style('warning');
        $this->lbl_ai_progress_detail
            ->text((string) ($statusData['detail'] ?? ''));
    }

    /**
     * @param array<string, mixed> $statusData
     */
    private function handleRenderingVideoStatus(array $statusData): void
    {
        $this->lbl_ai_progress_step
            ->text('🎬 ' . (string) ($statusData['message'] ?? 'Paso 2/2: Renderizando video con Remotion...'))
            ->style('info');
        $this->lbl_ai_progress_detail
            ->text((string) ($statusData['detail'] ?? 'Codificando fotogramas en MP4...'));

        if (is_array($statusData['parsed_script'] ?? null)) {
            $storyboard = app(ReelStoryboardFormatter::class)->format($statusData['parsed_script']);
            $this->lbl_storyboard_summary->text($storyboard);
        }
    }

    /**
     * @param array<string, mixed> $statusData
     */
    private function handleCompletedStatus(array $statusData): void
    {
        $this->tmr_reel_poll->stop();
        $this->state_active_job_id = '';

        $versionNumber = (int) ($statusData['version'] ?? (count($this->state_reel_versions) + 1));
        $jsonScript = (string) ($statusData['json_script'] ?? '');
        $feedbackApplied = isset($statusData['feedback_applied']) && is_string($statusData['feedback_applied'])
            ? $statusData['feedback_applied']
            : null;

        $storyboardMarkdown = app(ReelStoryboardFormatter::class)->format(
            $statusData['parsed_script'] ?? $jsonScript,
            $feedbackApplied
        );

        $this->state_reel_versions[] = [
            'version' => $versionNumber,
            'video_url' => (string) ($statusData['video_url'] ?? ''),
            'video_filename' => (string) ($statusData['video_filename'] ?? ''),
            'json_script' => $jsonScript,
            'scene_count' => (int) ($statusData['scene_count'] ?? 0),
            'duration_seconds' => (int) ($statusData['duration_seconds'] ?? 0),
            'storyboard_markdown' => $storyboardMarkdown,
            'feedback_applied' => $feedbackApplied,
        ];

        $this->state_current_version_index = count($this->state_reel_versions) - 1;

        $this->btn_ask_ai->loading(false)->label('🔄 Regenerar desde Notas')->enabled(true);
        $this->btn_refine_ai->loading(false)->label('✨ Aplicar Ajustes y Re-renderizar')->enabled(true);
        $this->ai_feedback->value('');

        $this->applyCurrentVersionToUI();
        $this->toast("¡Previsualización v{$versionNumber} lista!", 'success');
    }

    /**
     * @param array<string, mixed> $statusData
     */
    private function handleFailedStatus(array $statusData): void
    {
        $this->tmr_reel_poll->stop();
        $this->state_active_job_id = '';

        $this->lbl_ai_progress_step
            ->text('❌ ' . (string) ($statusData['message'] ?? 'Error al generar el video.'))
            ->style('danger');
        $this->lbl_ai_progress_detail
            ->text((string) ($statusData['detail'] ?? 'Intenta nuevamente o ajusta tus notas.'));

        $this->lbl_version_badge->text('Error en generación')->style('danger');
        $this->btn_ask_ai->loading(false)->label('✨ Reintentar con IA')->enabled(true);
        $this->btn_refine_ai->loading(false)->label('✨ Aplicar Ajustes y Re-renderizar')->enabled(true);

        $this->toast('Ocurrió un error al generar el video con IA.', 'error');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSelectPrevVersion(array $params): void
    {
        if ($this->state_current_version_index > 0) {
            $this->state_current_version_index--;
            $this->applyCurrentVersionToUI();
        }
    }

    /**
     * @param array<string, mixed> $params
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
        $versionNum = (int) ($versionData['version'] ?? ($this->state_current_version_index + 1));
        $sceneCount = (int) ($versionData['scene_count'] ?? 0);
        $durationSec = (int) ($versionData['duration_seconds'] ?? 0);
        $videoUrl = (string) ($versionData['video_url'] ?? '');
        $jsonScript = (string) ($versionData['json_script'] ?? '');
        $storyboard = (string) ($versionData['storyboard_markdown'] ?? '');

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
            $filename = (string) ($version['video_filename'] ?? '');
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

