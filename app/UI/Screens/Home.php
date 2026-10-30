<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Http\Requests\PostIdRequest;
use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use App\UI\Screens\Post\ReviewPost;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;
use App\Services\User\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Home extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Container $posts_list_container;

    protected Label $lbl_home_title;

    public function __construct(
        protected UserService $userService,
        protected PostServiceInterface $postService
    ) {}

    protected function buildBaseUI(Container $container, ...$params): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        $wrapper = UI::container('home_wrapper')
            ->centerHorizontal()
            ->plain();

        $wrapper->add($this->buildHeader($user));

        $this->posts_list_container = UI::container('posts_list_container')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(16));

        $this->renderPostsList();

        $wrapper->add($this->posts_list_container);
        $container->add($wrapper);
    }

    private function buildHeader(?User $user): Container
    {
        $header = UI::container('home_header')
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $header->add($this->buildTitleBox($user));
        $header->add($this->buildHeaderActionsBox($user));

        return $header;
    }

    private function buildTitleBox(?User $user): Container
    {
        $titleBox = UI::container('home_title_box')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $titleBox->add(
            $this->lbl_home_title = UI::label('lbl_home_title')
                ->text('📰 Últimos Posts')
                ->style('h3')
        );

        if ($user instanceof User && $user->isRegisteredInLobby()) {
            $this->lbl_home_title
                ->text("⏳ {$user->name}, aún estás en sala de espera.")
                ->style('warning');
        }

        return $titleBox;
    }

    private function buildHeaderActionsBox(?User $user): Container
    {
        $actionsBox = UI::container('home_actions_box')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(10))
            ->plain()
            ->padding(Spacing::zero());

        // if (! $user instanceof User) {
        //     $actionsBox->add(
        //         UI::button('btn_home_login')
        //             ->label('🔑 Iniciar Sesión')
        //             ->action('open_login')
        //             ->style('primary')
        //     );
        //     $actionsBox->add(
        //         UI::button('btn_home_register')
        //             ->label('📝 Registrarse')
        //             ->action('open_register')
        //             ->style('secondary')
        //     );

        //     return $actionsBox;
        // }

        if (Login::checkAccess()['allowed'] === true) {
            $actionsBox->add(
                UI::button('btn_go_login')
                    ->label('🔑 Iniciar Sesión')
                    ->action('open_login')
                    ->style('primary')
            );
        }

        if (Register::checkAccess()['allowed'] === true) {
            $actionsBox->add(
                UI::button('btn_go_register')
                    ->label('📝 Registrarse')
                    ->action('open_register')
                    ->style('secondary')
            );
        }

        if (PublishPost::checkAccess()['allowed'] === true) {
            $actionsBox->add(
                UI::button('btn_go_publish')
                    ->label('✍️ Publicar Post')
                    ->action('go_to_publish')
                    ->style('primary')
            );
        }

        if (UsersManager::checkAccess()['allowed'] === true) {
            $actionsBox->add(
                UI::button('btn_go_users')
                    ->label('👥 Gestionar Usuarios')
                    ->action('go_to_users')
                    ->style('secondary')
            );
        }

        // if ($this->userService->currentUserHasRole(['publisher', 'admin'])) {
        //     $actionsBox->add(
        //         UI::button('btn_go_publish')
        //             ->label('✍️ Publicar Post')
        //             ->action('go_to_publish')
        //             ->style('primary')
        //     );
        // }

        // if ($this->userService->currentUserHasRole('admin')) {
        //     $actionsBox->add(
        //         UI::button('btn_go_users')
        //             ->label('👥 Gestionar Usuarios')
        //             ->action('go_to_users')
        //             ->style('secondary')
        //     );
        // }

        return $actionsBox;
    }

    private function renderPostsList(): void
    {
        $this->posts_list_container->clear();

        $posts = $this->postService->getLatestPosts(Auth::user());

        if ($posts->isEmpty()) {
            $this->posts_list_container->add($this->buildEmptyPostsCard());

            return;
        }

        $isAdmin = $this->userService->currentUserHasRole('admin');
        $isReviewer = $this->userService->currentUserHasRole(['reviewer', 'admin']);
        $dim = AspectRatio::_16_9->dimensionsFor(width: 240);
        $height = Size::px($dim[1] + 40);

        /** @var PostDTO $post */
        foreach ($posts as $post) {
            $this->posts_list_container->add(
                $this->buildPostCard($post, $height, $isAdmin, $isReviewer)
            );
        }
    }

    private function buildEmptyPostsCard(): Container
    {
        $emptyCard = UI::container('posts_empty_card')
            ->card()
            ->padding(Spacing::px(32))
            ->alignItems(AlignItems::CENTER);

        $emptyCard->add(
            UI::label('lbl_no_posts')
                ->text('Aún no hay posts publicados.')
                ->style('h3')
                ->center()
        );

        return $emptyCard;
    }

    private function buildPostCard(PostDTO $post, Size $imageHeight, bool $isAdmin, bool $isReviewer): Container
    {
        $isOwner = $this->postService->isCurrentUserOwner($post->id, Auth::user());
        $backgroundColor = $post->background_color ?? '#7c3aed';
        $textColor = $post->text_color ?? '#ffffff';

        $postCard = UI::container("post_card_{$post->id}")
            ->card()
            ->layout(LayoutType::VERTICAL)
            ->minWidth(Size::px(320))->maxWidth(Size::px(320))
            ->minHeight(Size::px(360))->maxHeight(Size::px(360))
            ->padding(Spacing::px(8))
            ->gap(Spacing::px(6));

        $postCard->add(
            UI::image("post_img_{$post->id}")
                ->src($post->image ?? '')
                ->storage('uploads/images')
                ->alt((string) $post->title)
                ->width(Size::full())
                ->height($imageHeight)
                ->objectFit('scale-down')
                ->rounded(4)
                ->placeholderColors($backgroundColor, $textColor)
                ->placeholderAspect(AspectRatio::_16_9)
                ->placeholderId($post->id)
        );

        $postCard->add($this->buildPostCardHeader($post));

        $authorName = '';

        if ($this->postService->isCurrentUserOwner($post->id, Auth::user())) {
            $authorName = 'Tú';
        } elseif ($post->author) {
            $authorName = $post->author->name;
        }

        $createdAt = $post->createdAt->diffForHumans();

        $postCard->add(
            UI::label("post_meta_{$post->id}")
                ->text("Autor: {$authorName} • {$createdAt}")
                ->size('small')
                ->style('muted')
        );

        if (! empty($post->reviewer_comment) && ($isOwner || $isReviewer)) {
            $postCard->add(
                UI::label("post_review_note_{$post->id}")
                    ->text('💬 Obs: ' . (string) $post->reviewerComment)
                    ->style('warning')
                    ->size('small')
            );
        }

        $postCard->add($this->buildPostCardActions($post, $isOwner, $isAdmin, $isReviewer));

        return $postCard;
    }

    private function buildPostCardHeader(PostDTO $post): Container
    {
        $postHeader = UI::container("post_header_{$post->id}")
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(6));

        $postHeader->add(
            UI::label("post_title_{$post->id}")
                ->text($post->title)
                ->style('h4')
        );

        [$badgeText, $badgeStyle] = $this->resolveStatusBadge($post);

        $statusBadge = UI::label("post_status_{$post->id}")
            ->text($badgeText)
            ->variant('badge')
            ->style($badgeStyle)
            ->size('xs');

        $postHeader->add($statusBadge);

        return $postHeader;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveStatusBadge(PostDTO $post): array
    {
        $status = (string) ($post->status->value ?? 'draft');
        $jobStatus = (string) ($post->ai_job_status ?? 'idle');

        if ($status === 'draft' && in_array($jobStatus, ['generating_script', 'rendering_video'], true)) {
            return ['⚙️ Procesando IA', 'info'];
        }

        return match ($status) {
            'pending_review' => ['⏳ En revisión', 'info'],
            'changes_requested' => ['⚠️ Con observaciones', 'error'],
            'published' => ['🟢 Publicado', 'success'],
            'archived' => ['📦 Archivado', 'muted'],
            default => ['📝 Borrador', 'warning'],
        };
    }

    private function buildPostCardActions(PostDTO $post, bool $isOwner, bool $isAdmin, bool $isReviewer): Container
    {
        $postActionBox = UI::container("post_action_box_{$post->id}")
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->plain()
            ->gap(Spacing::px(6))
            ->padding(Spacing::zero());

        if ($isReviewer && ($post->status ?? '') === 'pending_review' && ! $isOwner) {
            $postActionBox->add(
                UI::button("btn_review_post_{$post->id}")
                    ->label('🔍 Revisar')
                    ->action('review_post', ['post_id' => (int) $post->id])
                    ->style('info')
                    ->size('small')
            );
        }

        $user = Auth::user();
        // if ($isOwner) {
        //     $editLabel = ($post->status ?? '') === 'changes_requested' ? '✏️ Corregir' : '✏️';
        //     $postActionBox->add(
        //         UI::button("btn_edit_post_{$post->id}")
        //             ->label($editLabel)
        //             ->action('edit_post', ['post_id' => (int) $post->id])
        //             ->style('secondary')
        //             ->size('small')
        //     );
        // }

        if (($user instanceOf User) && $user->can('update', $post)) {
            $editLabel = ($post->status ?? '') === 'changes_requested' ? '✏️ Corregir' : '✏️';
            $postActionBox->add(
                UI::button("btn_edit_post_{$post->id}")
                    ->label($editLabel)
                    ->action('edit_post', ['post_id' => (int) $post->id])
                    ->style('secondary')
                    ->size('small')
            );
        }

        if (($user instanceOf User) && $user->can('delete', $post)) {
            $postActionBox->add(
                UI::button("btn_delete_post_{$post->id}")
                    ->label('🗑️')
                    ->action('delete_post', ['post_id' => (int) $post->id])
                    ->style('danger')
                    ->size('small')
            );
        }

        return $postActionBox;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onOpenLogin(array $params): void
    {
        $this->openModal(Login::class);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onOpenRegister(array $params): void
    {
        $this->openModal(Register::class);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onGoToPublish(array $params): void
    {
        if ($this->postService->getLatestPosts(Auth::user())->isEmpty()) {
            $this->posts_list_container->clear();
        }

        $user = Auth::user();
        $draftId = $this->postService->createPost([
            'user_id' => (int) $user->id,
            'status' => 'draft',
            'from' => now()->format('Y-m-d'),
            'to' => now()->addDays(14)->format('Y-m-d'),
        ]);
        $post = $this->postService->findById($draftId);

        $isAdmin = $this->userService->currentUserHasRole('admin');
        $isReviewer = $this->userService->currentUserHasRole(['reviewer', 'admin']);
        $dim = AspectRatio::_16_9->dimensionsFor(width: 240);
        $height = Size::px($dim[1] + 40);

        $this->posts_list_container->add(
            $this->buildPostCard($post, $height, $isAdmin, $isReviewer)
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onGoToUsers(array $params): void
    {
        $this->navigate(UsersManager::class);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onEditPost(array $params): void
    {
        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        $this->navigate(PublishPost::class, params: ['post_id' => $postId]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onReviewPost(array $params): void
    {
        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        $this->navigate(ReviewPost::class, params: ['post_id' => $postId]);
    }

    /**
     * @param  PostIdRequest  $request
     */
    public function onDeletePost(PostIdRequest $request): void
    {
        try {
            $this->postService->deletePost($request->getPostId(), Auth::user());
            $this->toast('Post eliminado correctamente.', 'success');
            $this->removePostCardFromContainer($request->getPostId());
        } catch (ModelNotFoundException $e) {
            $this->toast('No se encontró el post a eliminar.', 'error');
        } catch (AuthorizationException $e) {
            $this->toast($e->getMessage(), 'error');
        } catch (\RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            $this->toast('Ocurrió un error al eliminar el post.', 'error');
        }
    }

    private function removePostCardFromContainer(int $postId): void
    {
        if (! isset($this->posts_list_container)) {
            return;
        }

        $postCard = $this->posts_list_container->findByName("post_card_{$postId}");
        if ($postCard !== null) {
            $this->posts_list_container->tryRemove($postCard->getId());
        }

        if ($this->posts_list_container->count() === 0) {
            $this->posts_list_container->add($this->buildEmptyPostsCard());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if (is_object($user) && isset($user->name)) {
            $this->lbl_home_title
                ->text("{$user->name}, aún estás en sala de espera.")
                ->style('warning');
        }
    }
}
