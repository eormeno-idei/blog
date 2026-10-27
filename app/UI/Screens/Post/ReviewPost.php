<?php

namespace App\UI\Screens\Post;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\Services\Post\ReelStoryboardFormatter;
use App\UI\Screens\Home;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class ReviewPost extends Screen
{
    protected int $state_post_id = 0;

    protected Textarea $reviewer_comment;

    protected Carousel $review_reel_player;

    protected Label $lbl_review_storyboard;

    public function __construct(
        protected PostServiceInterface $postService,
        protected ReelStoryboardFormatter $storyboardFormatter
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return self::requireRole(['reviewer', 'admin']);
    }

    public static function getMenuLabel(): string
    {
        return 'Revisar Propuesta';
    }

    public static function getMenuIcon(): ?string
    {
        return '🔍';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $rawPostId = $params['post_id'] ?? $this->getQueryParams()['post_id'] ?? null;
        if (isset($rawPostId) && is_numeric($rawPostId)) {
            $this->state_post_id = (int) $rawPostId;
        }

        if ($this->state_post_id <= 0) {
            return;
        }

        /** @var \stdClass|null $post */
        $post = $this->postService->findById($this->state_post_id);
        if (! $post) {
            $this->toast('Propuesta no encontrada.', 'error');
            $this->showInto(Home::class, force: true, updateBrowserUrl: true);

            return;
        }

        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(1180))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(20));

        $container->add($this->buildHeader());

        $workspaceRow = UI::container('review_workspace_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::START)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(24));

        $workspaceRow->add($this->buildProposalDetailsColumn($post));
        $workspaceRow->add($this->buildModerationColumn($post));

        $container->add($workspaceRow);
    }

    private function buildHeader(): Container
    {
        $header = UI::container('review_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(16));

        $header->add(
            UI::label('lbl_review_title')
                ->text('🔍 Revisión de Propuesta Institucional')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_review')
                ->style('secondary')
        );

        return $header;
    }

    private function buildProposalDetailsColumn(\stdClass $post): Container
    {
        $detailsCard = UI::container('review_details_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(600))
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $authorName = (string) ($post->author_name ?? 'Investigador');
        $fromDate = ! empty($post->from) ? substr((string) $post->from, 0, 10) : 'Inmediata';
        $toDate = ! empty($post->to) ? substr((string) $post->to, 0, 10) : 'Sin vencimiento';

        $detailsCard->add(
            UI::label('lbl_proposal_title')
                ->text((string) $post->title)
                ->style('h3')
        );

        $detailsCard->add(
            UI::label('lbl_proposal_meta')
                ->text("👤 Propuesto por: {$authorName}  |  📅 Vigencia: {$fromDate} → {$toDate}")
                ->style('info')
                ->size('small')
        );

        $detailsCard->add(
            UI::label('lbl_scenes_section')
                ->text('📋 Resumen de las Escenas Finales')
                ->style('primary')
                ->bold()
        );

        $storyboardText = $this->storyboardFormatter->format(
            is_string($post->ai_script ?? null) ? $post->ai_script : null
        );

        $this->lbl_review_storyboard = UI::label('lbl_review_storyboard')
            ->text($storyboardText)
            ->markdown(true);

        $detailsCard->add($this->lbl_review_storyboard);

        $detailsCard->add(
            UI::label('lbl_original_notes_header')
                ->text('📝 Notas Originales del Autor')
                ->style('secondary')
                ->bold()
                ->size('small')
        );

        $detailsCard->add(
            UI::label('lbl_original_notes_body')
                ->text((string) $post->content)
                ->style('muted')
                ->size('small')
        );

        return $detailsCard;
    }

    private function buildModerationColumn(\stdClass $post): Container
    {
        $modCard = UI::container('review_moderation_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(540))
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $modCard->add(
            UI::label('lbl_video_preview_title')
                ->text('🎬 Previsualización del Reel')
                ->style('h3')
        );

        $this->review_reel_player = UI::carousel('review_reel_player')
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

        if (! empty($post->reel_video) && is_string($post->reel_video)) {
            $this->review_reel_player->currentMedia([
                'id' => "review_reel_{$post->id}",
                'kind' => 'video',
                'url' => "/storage/reels/{$post->reel_video}",
                'mime' => 'video/mp4',
                'title' => (string) $post->title,
                'duration_ms' => 30000,
            ]);
        }

        $modCard->add($this->review_reel_player);

        $this->reviewer_comment = UI::textarea('reviewer_comment')
            ->label('Observaciones o Comentarios para el Investigador')
            ->placeholder('Escribe aquí si notas que falta o sobra algo en el texto o en las escenas...')
            ->value((string) ($post->reviewer_comment ?? ''))
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(110))
            ->maxLength(1000);

        $modCard->add($this->reviewer_comment);
        $modCard->add($this->buildDecisionButtonsRow());

        return $modCard;
    }

    private function buildDecisionButtonsRow(): Container
    {
        $actionsRow = UI::container('review_decision_actions')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(12))
            ->padding(Spacing::zero());

        $actionsRow->add(
            UI::button('btn_request_changes')
                ->label('↩️ Solicitar Modificaciones')
                ->action('request_changes')
                ->style('warning')
        );

        $actionsRow->add(
            UI::button('btn_approve_post')
                ->label('✅ Aprobar y Publicar')
                ->action('approve_post')
                ->style('success')
        );

        return $actionsRow;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onRequestChanges(array $params): void
    {
        $user = Auth::user();
        if (! $user instanceof User || $this->state_post_id <= 0) {
            return;
        }

        $rawComment = $params['reviewer_comment'] ?? $this->reviewer_comment->getValue();
        $comment = is_string($rawComment) ? trim($rawComment) : '';
        if ($comment === '') {
            $this->reviewer_comment->error('Debes ingresar un comentario explicando las modificaciones solicitadas.');
            $this->toast('Escribe un comentario para el autor antes de solicitar cambios.', 'warning');

            return;
        }

        $this->postService->requestChanges($this->state_post_id, (int) $user->id, $comment);
        $this->toast('Observaciones enviadas al autor.', 'info');
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onApprovePost(array $params): void
    {
        $user = Auth::user();
        if (! $user instanceof User || $this->state_post_id <= 0) {
            return;
        }

        $this->postService->approvePost($this->state_post_id, (int) $user->id);
        $this->toast('¡Publicación aprobada y activa para difusión!', 'success');
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCancelReview(array $params): void
    {
        $this->navigate(Home::class);
    }
}
