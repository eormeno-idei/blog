<?php

declare(strict_types=1);

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\UI\Screens\Concerns\HasAiReelStructureStep;
use App\UI\Screens\Concerns\HasPostDataStep;
use App\UI\Screens\Concerns\HasPostImageStep;
use App\UI\Screens\Concerns\HasReviewerResponseStep;
use App\UI\Screens\Concerns\HasReviewSubmissionStep;
use App\UI\Screens\Concerns\HasSummaryAndPublishStep;
use App\UI\Screens\Concerns\HasVideoGenerationStep;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Pantalla de asistente (Wizard) para la creación y publicación de posts en 7 pasos.
 *
 * @property Button $btn_save_post
 */
class PublishPost extends Screen
{
    use HasAiReelStructureStep;
    use HasPostDataStep;
    use HasPostImageStep;
    use HasReviewerResponseStep;
    use HasReviewSubmissionStep;
    use HasSummaryAndPublishStep;
    use HasVideoGenerationStep;

    protected Container $workspace_tabs;

    protected int $state_post_id = 0;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
        parent::__construct();
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $rawPostId = $params['post_id'] ?? $this->getQueryParams()['post_id'] ?? null;
        $explicitPostId = isset($rawPostId) && is_numeric($rawPostId)
            ? (int) $rawPostId
            : 0;

        if ($explicitPostId > 0) {
            $this->state_post_id = $explicitPostId;
        }

        $post = $this->resolvePost($explicitPostId > 0);
        if ($explicitPostId > 0 && $post === null) {
            $this->toast('Post no encontrado.', 'error');
            $this->navigate(Home::class);

            return;
        }

        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(980))
            ->centerHorizontal();

        $container->add($this->buildReelPollTimer());
        $container->add($this->buildVideoPollTimer());
        $container->add($this->buildHeader());

        $this->buildWizardTabs($post);
        $container->add($this->workspace_tabs);

        $this->restoreReelStudioFromPost($post);
        $this->configureInitialTabState($post);
    }

    private function buildHeader(): Container
    {
        $header = UI::container('publish_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(16));

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        return $header;
    }

    private function buildWizardTabs(?PostDTO $post): void
    {
        $hasAiScript = $post !== null && ! empty($post->aiScript);
        $hasVideo = $post !== null && ! empty($post->reelVideo);
        $statusVal = $post !== null ? (is_string($post->status) ? $post->status : $post->status->value) : 'draft';

        $this->workspace_tabs = UI::container('workspace_tabs')
            ->card()
            ->width(Size::full())
            ->tabs([
                'form_tab' => [
                    'label' => '1. Datos',
                    'disabled' => false,
                ],
                'step_image_tab' => [
                    'label' => '2. Imagen',
                    'disabled' => $post === null,
                ],
                'reel_studio_tab' => [
                    'label' => '3. Estructurar Reel con IA',
                    'disabled' => ! $hasAiScript && ($post === null || $statusVal === 'draft'),
                ],
                'step_review_submission_tab' => [
                    'label' => '4. Enviar a Revisión',
                    'disabled' => ! $hasAiScript,
                ],
                'step_reviewer_response_tab' => [
                    'label' => '5. Respuesta del Revisor',
                    'disabled' => ! in_array($statusVal, ['pending_review', 'changes_requested', 'published'], true),
                ],
                'step_video_generation_tab' => [
                    'label' => '6. Generación de Video',
                    'disabled' => ! in_array($statusVal, ['published', 'reel_generated', 'reel_generation_pending'], true) && ! $hasVideo,
                ],
                'step_summary_publish_tab' => [
                    'label' => '7. Resumen y Publicar',
                    'disabled' => ! $hasVideo,
                ],
            ], 'form_tab');

        $this->workspace_tabs->add($this->buildDataStepCard($post), tab: 'form_tab');
        $this->workspace_tabs->add($this->buildImageStepCard($post), tab: 'step_image_tab');
        $this->workspace_tabs->add($this->buildAiReelStructureStepCard($post), tab: 'reel_studio_tab');
        $this->workspace_tabs->add($this->buildReviewSubmissionStepCard($post), tab: 'step_review_submission_tab');
        $this->workspace_tabs->add($this->buildReviewerResponseStepCard($post), tab: 'step_reviewer_response_tab');
        $this->workspace_tabs->add($this->buildVideoGenerationStepCard($post), tab: 'step_video_generation_tab');
        $this->workspace_tabs->add($this->buildSummaryAndPublishStepCard($post), tab: 'step_summary_publish_tab');
    }

    private function configureInitialTabState(?PostDTO $post): void
    {
        if ($post === null) {
            return;
        }

        $statusVal = is_string($post->status) ? $post->status : $post->status->value;

        if ($statusVal === 'changes_requested') {
            $this->enableStepTab('step_reviewer_response_tab', activate: false);
            $this->workspace_tabs->activeTab('form_tab');

            if (isset($this->btn_save_post)) {
                $this->btn_save_post->enabled(true);
            }

            return;
        }

        if ($statusVal === 'pending_review') {
            $this->enableStepTab('step_reviewer_response_tab', activate: true);

            return;
        }

        if (! empty($post->reelVideo)) {
            $this->enableStepTab('step_video_generation_tab', activate: false);
            $this->enableStepTab('step_summary_publish_tab', activate: false);
        }

        if (! empty($post->aiScript) && isset($this->btn_save_post)) {
            $this->btn_save_post->enabled(true);
        }
    }

    public function enableStepTab(string $tabId, bool $activate = false): void
    {
        if (! isset($this->workspace_tabs)) {
            return;
        }

        $this->workspace_tabs->disableTab($tabId, false);

        if ($activate) {
            $this->workspace_tabs->activeTab($tabId);
        }
    }

    public function goToStep(string $tabId): void
    {
        $this->enableStepTab($tabId, activate: true);
    }

    protected function enableReelStudioTab(bool $activate = false): void
    {
        $this->enableStepTab('reel_studio_tab', $activate);
    }

    private function resolvePost(bool $isExplicit = false): ?PostDTO
    {
        if ($this->state_post_id > 0) {
            $existing = $this->postService->findById($this->state_post_id);
            if ($existing !== null || $isExplicit) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function resolveCandidatePostId(array $params = []): int
    {
        $candidate = $params['post_id'] ?? $this->getQueryParams()['post_id'] ?? $this->state_post_id;

        return is_numeric($candidate) && (int) $candidate > 0 ? (int) $candidate : 0;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCancelPost(array $params): void
    {
        $this->stopActiveReelJobPolling();
        $this->navigate(Home::class);
    }
}
