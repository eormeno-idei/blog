<?php

namespace App\UI\Screens\Post;

use App\Contracts\PostServiceInterface;
use App\Http\Requests\PostRequest;
use App\Models\User;
use App\UI\Screens\Concerns\InteractsWithReelStudio;
use App\UI\Screens\Home;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class EditPost extends Screen
{
    use InteractsWithReelStudio;

    protected ?Input $post_title;
    protected ?Input $post_content;
    protected Input $post_from;
    protected Input $post_to;
    protected ?Uploader $post_image;
    protected Button $btn_ask_ai;
    protected Button $btn_save_post;

    protected int $state_post_id = 0;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return self::requireRole(['publisher', 'admin']);
    }

    public static function getMenuLabel(): string
    {
        return 'Editar Post';
    }

    public static function getMenuIcon(): ?string
    {
        return '✏️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        /** @var array<string, mixed> $params */
        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : $this->state_post_id;

        if ($postId <= 0) {
            return;
        }

        $this->state_post_id = $postId;
        $post = $this->postService->findById($postId);
        if (! $post) {
            $this->toast('Post no encontrado.', 'error');
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

        $container->add($this->buildReelPollTimer());
        $container->add($this->buildHeader());

        if (! empty($post->reviewer_comment)) {
            $container->add($this->buildReviewerCommentAlert($post));
        }

        $workspaceRow = UI::container('edit_workspace_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::START)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(24));

        $workspaceRow->add($this->buildFormColumn($post));
        $workspaceRow->add($this->buildAiStudioCard());

        $container->add($workspaceRow);

        $this->preloadExistingReel(
            is_string($post->ai_script ?? null) ? $post->ai_script : null,
            is_string($post->reel_video ?? null) ? $post->reel_video : null
        );
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
            UI::label('lbl_publish_title')
                ->text('✏️ Editar Propuesta y Reel')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_edit')
                ->style('secondary')
        );

        return $header;
    }

    private function buildReviewerCommentAlert(object $post): Container
    {
        $reviewerName = (string) ($post->reviewer_name ?? 'Revisor');
        $comment = (string) ($post->reviewer_comment ?? '');

        $alertCard = UI::container('reviewer_feedback_alert')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(6));

        $alertCard->add(
            UI::label('lbl_reviewer_alert_title')
                ->text("⚠️ Observaciones de Revisión ({$reviewerName}):")
                ->style('warning')
                ->bold()
        );

        $alertCard->add(
            UI::label('lbl_reviewer_alert_body')
                ->text($comment)
                ->style('default')
        );

        return $alertCard;
    }

    private function buildFormColumn(object $post): Container
    {
        $formCard = UI::container('publish_form_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(600))
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título atractivo...')
            ->required(true)
            ->value((string) $post->title)
            ->width(Size::full());

        $this->post_content = UI::input('post_content')
            ->label('Contenido / Notas base')
            ->placeholder('Desarrolla el contenido de tu publicación...')
            ->required(true)
            ->value((string) $post->content)
            ->width(Size::full());

        $this->ai_response = UI::input('ai_response')
            ->type('hidden')
            ->value((string) ($post->ai_script ?? ''));

        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect(AspectRatio::_16_9)
            ->size(2)
            ->label('Imagen de Portada (opcional)');

        $formCard->add($this->post_title);
        $formCard->add($this->post_content);
        $formCard->add($this->buildDateRangeRow($post));
        $formCard->add($this->ai_response);
        $formCard->add($this->post_image);
        $formCard->add($this->buildActionsRow());

        return $formCard;
    }

    private function buildDateRangeRow(object $post): Container
    {
        $datesRow = UI::container('edit_dates_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(12));

        $fromVal = ! empty($post->from) ? substr((string) $post->from, 0, 10) : now()->format('Y-m-d');
        $toVal = ! empty($post->to) ? substr((string) $post->to, 0, 10) : now()->addDays(14)->format('Y-m-d');

        $this->post_from = UI::input('post_from')
            ->type('date')
            ->label('Fecha de Inicio de Publicación')
            ->value($fromVal)
            ->width(Size::full());

        $this->post_to = UI::input('post_to')
            ->type('date')
            ->label('Fecha de Finalización')
            ->value($toVal)
            ->width(Size::full());

        $datesRow->add($this->post_from);
        $datesRow->add($this->post_to);

        return $datesRow;
    }

    private function buildActionsRow(): Container
    {
        $actionsRow = UI::container('publish_actions_row')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(12))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $this->btn_ask_ai = UI::button('btn_ask_ai')
            ->label('✨ Generar Reel con IA')
            ->action('ask_ai')
            ->style('info');

        $rightActions = UI::container('edit_right_actions')
            ->plain()
            ->shadow(false)
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(10))
            ->padding(Spacing::zero());

        $rightActions->add(
            UI::button('btn_cancel_post')
                ->label('Cancelar')
                ->action('cancel_edit')
                ->style('secondary')
        );

        $this->btn_save_post = UI::button('btn_save_post')
            ->label('📤 Guardar y Reenviar a Revisión')
            ->action('save_post')
            ->style('primary');

        $rightActions->add($this->btn_save_post);
        $actionsRow->add($this->btn_ask_ai);
        $actionsRow->add($rightActions);

        return $actionsRow;
    }

    public function onSavePost(PostRequest $request): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');

            return;
        }

        $this->stopActiveReelJobPolling();

        $imageFilename = $this->resolveUploadedImageFilename($request->all());
        $currentVersion = $this->getCurrentVersionData();
        $videoFilename = is_string($currentVersion['video_filename'] ?? null) && $currentVersion['video_filename'] !== ''
            ? $currentVersion['video_filename']
            : null;
        $aiScript = is_string($currentVersion['json_script'] ?? null) && $currentVersion['json_script'] !== ''
            ? $currentVersion['json_script']
            : ((string) ($this->ai_response->get('value') ?? '') ?: null);

        $this->cleanupDiscardedReelFiles($videoFilename);

        $payload = [
            'title' => $request->title(),
            'content' => $request->content(),
            'from' => $request->fromDate(),
            'to' => $request->toDate(),
            'status' => 'pending_review',
            'ai_script' => $aiScript,
            'reel_video' => $videoFilename,
        ];

        if ($imageFilename !== null) {
            $payload['image'] = $imageFilename;
        }

        if ($this->state_post_id > 0) {
            $this->postService->updatePost($this->state_post_id, $payload);
        } else {
            $this->postService->createPost(array_merge($payload, [
                'user_id' => (int) $user->id,
                'image' => $imageFilename,
            ]));
        }

        $this->toast('¡Propuesta actualizada y enviada a revisión!', 'success');
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function resolveUploadedImageFilename(array $requestData): ?string
    {
        $confirmedImage = $this->post_image?->confirm($requestData, 'images');

        if (is_string($confirmedImage) && $confirmedImage !== '') {
            return $confirmedImage;
        }

        if (is_array($confirmedImage) && isset($confirmedImage[0]) && is_string($confirmedImage[0])) {
            return $confirmedImage[0];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCancelEdit(array $params): void
    {
        $this->stopActiveReelJobPolling();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }
}
