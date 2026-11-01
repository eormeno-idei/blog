<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\DTOs\ReelGenerationStatus;
use App\Jobs\GenerateReelPreviewJob;
use App\Jobs\RenderReelVideoJob;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Timer;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Step 6: Generación de video (Renderizado MP4 mediante Remotion).
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 *
 * @method void goToStep(string $tabId)
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method static toast(string $message, string $type = 'info')
 */
trait HasVideoGenerationStep
{
    protected ?Timer $tmr_video_poll = null;

    protected ?Container $video_progress_box = null;

    protected ?Label $lbl_video_progress_step = null;

    protected ?Label $lbl_video_progress_detail = null;

    protected ?Button $btn_generate_video = null;

    protected ?Carousel $video_final_player = null;

    protected ?Button $btn_step6_back = null;

    protected ?Button $btn_step6_next = null;

    protected string $state_active_video_job_id = '';

    protected function buildVideoPollTimer(): Timer
    {
        $this->tmr_video_poll = UI::timer('tmr_video_poll')
            ->action('check_video_status')
            ->every(3000)
            ->enabled(false);

        return $this->tmr_video_poll;
    }

    protected function buildVideoGenerationStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_video_generation_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(760))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $card->add(
            UI::label('lbl_video_step_title')
                ->text('🎬 Generación de Video Final (Remotion)')
                ->style('h3')
        );

        $card->add(
            UI::label('lbl_video_step_help')
                ->text('Renderiza las animaciones y tipografías en un video MP4 vertical (9:16) listo para cartelería digital e Instagram Reels.')
                ->style('muted')
        );

        $card->add($this->buildVideoProgressBox());

        $hasVideo = $post !== null && ! empty($post->reelVideo);
        $this->video_final_player = UI::carousel('video_final_player')
            ->modeManual()
            ->loop(true)
            ->showPrev(false)
            ->showNext(false)
            ->hideIndicators()
            ->width(Size::px(315))
            ->maxWidth(Size::px(315))
            ->height(Size::px(560))
            ->aspect(AspectRatio::_9_16)
            ->objectFit('contain')
            ->visible($hasVideo);

        if ($post !== null && ! empty($post->reelVideo)) {
            $videoFilename = (string) $post->reelVideo;
            $this->video_final_player->currentMedia([
                'id' => "video_post_{$post->id}",
                'kind' => 'video',
                'url' => "/storage/reels/{$videoFilename}",
                'mime' => 'video/mp4',
                'title' => $post->title,
                'duration_ms' => 15000,
            ]);
        }

        $card->add($this->video_final_player);
        $card->add($this->buildStep6ActionsRow($hasVideo));

        return $card;
    }

    private function buildVideoProgressBox(): Container
    {
        $this->video_progress_box = UI::container('video_progress_box')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(8));

        $this->lbl_video_progress_step = UI::label('lbl_video_progress_step')
            ->text('🎬 Presiona "Generar Video con Remotion" para comenzar el renderizado.')
            ->style('info')
            ->bold();

        $this->lbl_video_progress_detail = UI::label('lbl_video_progress_detail')
            ->text('El renderizado en Remotion tomará unos segundos para codificar todas las escenas.')
            ->style('muted')
            ->size('small');

        $this->video_progress_box->add($this->lbl_video_progress_step);
        $this->video_progress_box->add($this->lbl_video_progress_detail);

        return $this->video_progress_box;
    }

    private function buildStep6ActionsRow(bool $hasVideo): Container
    {
        $actions = UI::container('step6_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->padding(Spacing::zero());

        $this->btn_step6_back = UI::button('btn_step6_back')
            ->label('← Atrás: Respuesta del Revisor')
            ->action('step6_back')
            ->style('secondary');

        $this->btn_generate_video = UI::button('btn_generate_video')
            ->label($hasVideo ? '🔄 Regenerar Video MP4' : '🎬 Generar Video con Remotion')
            ->action('render_video')
            ->style('primary');

        $this->btn_step6_next = UI::button('btn_step6_next')
            ->label('Siguiente: Resumen y Publicar →')
            ->action('step6_next')
            ->style('success')
            ->enabled($hasVideo);

        $actions->add($this->btn_step6_back);
        $actions->add($this->btn_generate_video);
        $actions->add($this->btn_step6_next);

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep6Back(array $params): void
    {
        $this->goToStep('step_reviewer_response_tab');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep6Next(array $params): void
    {
        $this->enableStepTab('step_summary_publish_tab', activate: true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onRenderVideo(array $params): void
    {
        if ($this->state_post_id <= 0) {
            $this->toast('Post no válido para renderizar video.', 'error');

            return;
        }

        if ($this->state_active_video_job_id !== '') {
            $this->toast('Ya hay un renderizado en proceso. Espera un momento.', 'info');

            return;
        }

        $postId = $this->state_post_id;
        $this->state_active_video_job_id = (string) $postId;

        $post = $this->postService->findById($postId);
        $jsonScript = $post !== null ? $post->aiScript : null;
        if (empty($jsonScript)) {
            $this->toast('Debes estructurar el guion con IA antes de generar el video.', 'warning');
            $this->state_active_video_job_id = '';

            return;
        }

        $this->lbl_video_progress_step
            ?->text('🎬 Renderizando video con Remotion...')
            ?->style('warning');
        $this->lbl_video_progress_detail
            ?->text('Codificando escenas y efectos visuales en formato MP4.');
        $this->btn_generate_video?->loading(true, 'Renderizando...')?->enabled(false);

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $jsonScript, true);
        /** @var array<string, mixed> $parsedScript */
        $parsedScript = is_array($decoded) ? $decoded : [];

        GenerateReelPreviewJob::putStatus($postId, ReelGenerationStatus::renderingVideo(
            version: 1,
            sceneCount: 1,
            durationSeconds: 15,
            jsonScript: (string) $jsonScript,
            parsedScript: $parsedScript,
        ));

        RenderReelVideoJob::dispatch($postId, 1);
        $this->tmr_video_poll?->start();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCheckVideoStatus(array $params): void
    {
        if ($this->state_active_video_job_id === '') {
            $this->tmr_video_poll?->stop();

            return;
        }

        $postId = (int) $this->state_active_video_job_id;
        $status = GenerateReelPreviewJob::getStatus($postId);
        if ($status === null) {
            return;
        }

        if ($status->isCompleted() && ! empty($status->videoFilename)) {
            $this->tmr_video_poll?->stop();
            $this->state_active_video_job_id = '';

            $this->lbl_video_progress_step
                ?->text('✅ ¡Video generado con éxito!')
                ?->style('success');
            $this->lbl_video_progress_detail
                ?->text("Archivo listo: {$status->videoFilename} ({$status->durationSeconds}s)");

            $this->btn_generate_video?->loading(false)?->label('🔄 Regenerar Video MP4')?->enabled(true);
            $this->btn_step6_next?->enabled(true);

            $this->video_final_player?->visible(true);
            $this->video_final_player?->currentMedia([
                'id' => "video_rendered_{$postId}",
                'kind' => 'video',
                'url' => "/storage/reels/{$status->videoFilename}?v=".time(),
                'mime' => 'video/mp4',
                'title' => 'Video Final',
                'duration_ms' => max(1000, ((int) ($status->durationSeconds ?? 15)) * 1000),
            ]);

            $this->toast('¡Video final renderizado con éxito!', 'success');
        } elseif ($status->isFailed()) {
            $this->tmr_video_poll?->stop();
            $this->state_active_video_job_id = '';

            $this->lbl_video_progress_step
                ?->text('❌ Error al renderizar el video')
                ?->style('danger');
            $this->lbl_video_progress_detail
                ?->text($status->detail ?? 'Ocurrió un error en Remotion al procesar el video.');

            $this->btn_generate_video?->loading(false)?->label('🎬 Reintentar Generación de Video')?->enabled(true);
            $this->toast('Error al renderizar el video.', 'error');
        }
    }
}
