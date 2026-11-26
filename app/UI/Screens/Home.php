<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Enums\PostStatus;
use App\Http\Requests\PostIdRequest;
use App\Models\User;
use App\Services\Post\PostCardGridService;
use App\Services\User\UserService;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use App\UI\Screens\Post\ReviewPost;
use Idei\Usim\Components\CardGrid;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Style;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class Home extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected CardGrid $posts_grid;

    public function __construct(
        protected UserService $userService,
        protected PostServiceInterface $postService,
        protected PostCardGridService $postCardGridService,
    ) {}

    protected function startingUI(): Container
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user instanceof User && $user->isRegisteredInLobby()) {
            $this->posts_grid
                ->title($this->t('.waiting_room_title', ['name' => $user->name]))
                ->titleStyle(Style::WARNING)
                ->subtitle($this->t('.waiting_room_subtitle'));
        }

        $this->populateHeaderActions($user);

        if ($this->canFilterByStatus($user)) {
            $this->posts_grid->addSelectFilter(
                name: 'status',
                label: 'Estado',
                options: [
                    PostStatus::DRAFT->value => '📝 Borrador',
                    PostStatus::PENDING_REVIEW->value => '⏳ En revisión',
                    PostStatus::CHANGES_REQUESTED->value => '⚠️ Con observaciones',
                    PostStatus::PUBLISHED->value => '🟢 Publicado',
                    PostStatus::ARCHIVED->value => '📦 Archivado',
                ],
                placeholder: 'Estado: Todos',
            );
        }

        return $this->layoutContainer();
    }

    private function populateHeaderActions(?User $user): void
    {
        if ($user === null) {
            $this->addScreenHeaderAction(Login::class, 'open_login', Style::PRIMARY, 'solid');
            $this->addScreenHeaderAction(Register::class, 'open_register', Style::SECONDARY, 'outline');
        }

        $this->addScreenHeaderAction(PublishPost::class, 'go_to_publish', Style::PRIMARY, 'solid');
        $this->addScreenHeaderAction(UsersManager::class, 'go_to_users', Style::SECONDARY, 'outline');
    }

    /**
     * @param  class-string<Screen>  $screenClass
     */
    private function addScreenHeaderAction(
        string $screenClass,
        string $action,
        Style $style,
        string $variant,
    ): void {
        if (! $screenClass::canAccess()) {
            return;
        }

        $this->posts_grid->addHeaderAction(
            label: $screenClass::getMenuLabel(),
            action: $action,
            icon: $screenClass::getMenuIcon(),
            style: $style,
            variant: $variant,
        );
    }

    private function canFilterByStatus(?User $user): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        return $user->isRoot()
            || $user->can('post.view.any')
            || $user->can('post.view.own')
            || $user->can('post.review')
            || $user->hasRole(['admin', 'reviewer', 'publisher'])
            || $this->userService->currentUserHasRole(['admin', 'reviewer', 'publisher']);
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

    public function onGoToPublish(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $draftId = $this->postService->createPost([
            'user_id' => is_numeric($user->getAuthIdentifier()) ? (int) $user->getAuthIdentifier() : 0,
            'status' => 'draft',
        ]);
        $post = $this->postService->findById($draftId);
        if ($post === null) {
            return;
        }

        $this->posts_grid->prependCard(
            $this->postCardGridService->toCardItemDTO($post, $user)
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
     * @param  int  $postId
     */
    public function onEditPost(int $postId): void
    {
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
        $user = Auth::user();
        if ($user === null) {
            $this->toast('Debes iniciar sesión para eliminar un post.', 'error');

            return;
        }

        try {
            $this->postService->deletePost($request->getPostId(), $user);
            $this->toast('Post eliminado correctamente.', 'success');
            $this->posts_grid->removeCard($request->getPostId());
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
}
