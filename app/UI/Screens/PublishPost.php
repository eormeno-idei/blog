<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Http\Requests\PostRequest;
use App\Models\User;
use App\UI\Screens\Concerns\InteractsWithReelStudio;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
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

class PublishPost extends Screen
{
    use InteractsWithReelStudio;

    protected Input $post_id;
    protected Input $post_title;
    protected Textarea $post_content;
    protected Input $post_from;
    protected Input $post_to;
    protected Uploader $post_image;
    protected Button $btn_ask_ai;
    protected Button $btn_save_post;

    protected int $state_post_id = 0;
    protected int $store_editing_post_id = 0;

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
        return 'Publicar Post';
    }

    public static function getMenuIcon(): ?string
    {
        return '✍️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        /** @var array<string, mixed> $params */
        $explicitPostId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        $post = $this->resolveOrCreatePost($params);
        if ($explicitPostId > 0 && $post === null) {
            $this->toast('Post no encontrado.', 'error');
            $this->showInto(Home::class, force: true, updateBrowserUrl: true);

            return;
        }

        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(980))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(20));

        $container->add($this->buildReelPollTimer());
        $container->add($this->buildHeader());

        if ($post !== null && ! empty($post->reviewer_comment)) {
            $container->add($this->buildReviewerCommentAlert($post));
        }

        $container->add($this->buildWorkspaceTabs($this->buildFormColumn($post)));

        if ($post !== null) {
            $this->restoreReelStudioFromPost($post);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveOrCreatePost(array $params): ?object
    {
        if (! empty($params['create_new'])) {
            return $this->createInitialDraftPost();
        }

        $candidateId = $this->resolveCandidatePostId($params);
        if ($candidateId > 0) {
            $existing = $this->postService->findById($candidateId);
            if ($existing !== null || isset($params['post_id'])) {
                $this->syncActivePostId($candidateId);

                return $existing;
            }
        }

        return $this->createInitialDraftPost();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveCandidatePostId(array $params = []): int
    {
        $candidates = [
            $params['post_id'] ?? null,
            request()->input('parameters.post_id'),
            $this->state_post_id,
            $this->store_editing_post_id,
            session('editing_post_id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    private function createInitialDraftPost(): ?object
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }

        $draftId = $this->postService->createPost([
            'user_id' => (int) $user->id,
            'title' => 'Borrador sin título',
            'content' => self::defaultNotesTemplate(),
            'status' => 'draft',
            'from' => now()->format('Y-m-d'),
            'to' => now()->addDays(14)->format('Y-m-d'),
        ]);

        $this->syncActivePostId($draftId);

        return $this->postService->findById($draftId);
    }

    private function syncActivePostId(int $postId): void
    {
        $this->state_post_id = $postId;
        $this->store_editing_post_id = $postId;
        session(['editing_post_id' => $postId]);
    }

    private function clearActivePostContext(): void
    {
        $this->store_editing_post_id = 0;
        session()->forget('editing_post_id');
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
                ->text('✍️ Nueva Propuesta de Publicación y Reel')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        return $header;
    }

    private function buildFormColumn(?object $post = null): Container
    {
        $formCard = UI::container('publish_form_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(760))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $formCard->add(
            UI::label('lbl_form_section_title')
                ->text('1. Datos de la Propuesta')
                ->style('h3')
        );

        $initialTitle = $post !== null && (string) $post->title !== 'Borrador sin título'
            ? (string) $post->title
            : '';

        $initialContent = $post !== null && trim((string) ($post->content ?? '')) !== ''
            ? (string) $post->content
            : $this->defaultNotesValue();

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título atractivo...')
            ->value($initialTitle)
            ->required(true)
            ->width(Size::full())
            ->onChange('on_title_saved');

        $this->post_content = UI::textarea('post_content')
            ->label('Notas base para el Post y el Reel')
            ->placeholder('Escribe las notas o información del evento/proyecto…')
            ->value($initialContent)
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(240))
            ->maxLength(2000)
            ->helpText('Máximo 2000 caracteres. La IA usará estas notas para estructurar el guion del Reel.')
            ->onChange('on_plain_saved');

        $this->post_id = UI::input('post_id')
            ->type('hidden')
            ->value((string) $this->state_post_id);

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

        $formCard->add($this->post_id);
        $formCard->add($this->post_title);
        $formCard->add($this->post_content);
        $formCard->add($this->buildDateRangeRow($post));
        $formCard->add($this->ai_response);
        $formCard->add($this->post_image);
        $formCard->add($this->buildActionsRow());

        return $formCard;
    }

    private function buildDateRangeRow(?object $post = null): Container
    {
        $datesRow = UI::container('publish_dates_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(12));

        $fromVal = $post !== null && ! empty($post->from)
            ? substr((string) $post->from, 0, 10)
            : now()->format('Y-m-d');
        $toVal = $post !== null && ! empty($post->to)
            ? substr((string) $post->to, 0, 10)
            : now()->addDays(14)->format('Y-m-d');

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

        $this->btn_save_post = UI::button('btn_save_post')
            ->label('📤 Enviar a Revisión')
            ->action('save_post')
            ->style('primary')
            ->enabled(false);

        $actionsRow->add($this->btn_ask_ai);
        $actionsRow->add($this->btn_save_post);

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
        $videoFilename = is_string($currentVersion['video_filename'] ?? null)
            ? $currentVersion['video_filename']
            : null;
        $aiScript = is_string($currentVersion['json_script'] ?? null)
            ? $currentVersion['json_script']
            : ((string) ($this->ai_response->get('value') ?? '') ?: null);

        $this->cleanupDiscardedReelFiles($videoFilename);

        $payload = [
            'user_id' => (int) $user->id,
            'title' => $request->title(),
            'content' => $request->content(),
            'image' => $imageFilename,
            'background_color' => '#32637b',
            'text_color' => '#000000',
            'background_image' => null,
            'aspect_ratio' => '9:16',
            'from' => $request->fromDate(),
            'to' => $request->toDate(),
            'status' => 'pending_review',
            'ai_script' => $aiScript,
            'reel_video' => $videoFilename,
        ];

        $targetPostId = $this->resolveCandidatePostId($request->all());
        if ($targetPostId > 0) {
            $this->state_post_id = $targetPostId;
            $this->postService->updatePost($targetPostId, $payload);
        } else {
            $this->state_post_id = $this->postService->createPost($payload);
        }

        $this->clearActivePostContext();
        $this->toast('¡Propuesta enviada a revisión correctamente!', 'success');
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function resolveUploadedImageFilename(array $requestData): ?string
    {
        $confirmedImage = $this->post_image->confirm($requestData, 'images');

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
    public function onCancelPost(array $params): void
    {
        $this->stopActiveReelJobPolling();
        $this->clearActivePostContext();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onTitleSaved(array $params): void
    {
        $rawValue = $params['value'] ?? $params['post_title'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $this->post_title->value($value);

        $targetPostId = $this->resolveCandidatePostId($params);
        if ($targetPostId > 0) {
            $this->syncActivePostId($targetPostId);
            $this->postService->updatePost($targetPostId, [
                'title' => $value !== '' ? $value : 'Borrador sin título',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPlainSaved(array $params): void
    {
        $rawValue = $params['value'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $this->post_content->value($value);

        $targetPostId = $this->resolveCandidatePostId($params);
        if ($targetPostId > 0 && $value !== '') {
            $this->syncActivePostId($targetPostId);
            $this->postService->updatePost($targetPostId, [
                'content' => $value,
            ]);
        }
    }

    private function defaultNotesValue(): string
    {
        return self::defaultNotesTemplate();
    }

    public static function defaultNotesTemplate(): string
    {
        return <<<'EOT'
Proyecto de Desarrollo Tecnológico y Social (PDTS)
Universidad Nacional de San Juan
Secretaría de Ciencia, Tecnología e Innovación – Gobierno de San Juan
Convocatoria 2025

1. DENOMINACIÓN DEL PROYECTO
Innovación en Desarrollo de Software: Validación de Arquitectura Backend-Driven UI en la aplicación "+65 Agenda Salud"

2. RESUMEN
El presente proyecto tiene por objeto la validación científica y transferencia al sector productivo de una tecnología de desarrollo de software basada en arquitectura Backend-Driven. Esta herramienta, resultado de un Proyecto de Investigación (PIC) previo del Instituto de Informática, propone centralizar la lógica de interfaz en el servidor para acelerar los tiempos de desarrollo de software. La propuesta intentará validar el framework en dos fases de ejecución: primero, se utilizará el framework para construir y desplegar "+65 Agenda Salud", una aplicación móvil de autogestión sanitaria para adultos mayores. Segundo, se ejecutará un experimento controlado en dos empresas de Cuyo: Aconcagua Software Factory y Nodos Hub, en las cuales se comparará la productividad de este enfoque frente al desarrollo tradicional, consolidando la transferencia de la tecnología a la industria de software regional.
EOT;
    }
}
