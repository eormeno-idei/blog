<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Home extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected ?Container $posts_list_container = null;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
    }

    public static function getMenuLabel(): string
    {
        return 'Inicio';
    }

    public static function getMenuIcon(): ?string
    {
        return '🏠';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->renderHomeContent($container);
    }

    protected function postLoadUI(): void
    {
        $this->container->clear();
        $this->renderHomeContent($this->container);
    }

    private function renderHomeContent(Container $container): void
    {
        $user = Auth::user();

        // Si el usuario tiene únicamente el rol "registered", sólo debe ver la pantalla Registered
        if ($user instanceof User && $user->isRegisteredInLobby()) {
            $container
                ->plain()
                ->padding(Spacing::zero());
            Screen::make(Registered::class)->clearCachedScreenSnapshot();
            Screen::embedInto(Registered::class, $container);
            return;
        }

        $container
            ->maxWidth(Size::px(960))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(20))
            ->plain();

        $this->posts_list_container = UI::container('posts_list_container')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->flexWrap('wrap')
            ->gap(Spacing::px(20))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $this->renderPostsList();

        $container->add($this->posts_list_container);
    }

    /**
     * Comprueba si el usuario autenticado posee alguno de los roles indicados.
     *
     * @param string|list<string> $roles
     */
    private function currentUserHasRole(string|array $roles): bool
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->isRoot()) {
            return true;
        }

        $roleList = is_array($roles) ? $roles : [$roles];

        return $user->globalRoles()->whereIn('name', $roleList)->exists();
    }

    private function renderPostsList(): void
    {
        $this->posts_list_container->clear();

        $posts = $this->postService->getLatestPosts();

        if ($posts->isEmpty()) {
            $emptyCard = UI::container('posts_empty_card')
                ->card()
                ->width(Size::full())
                ->padding(Spacing::px(32))
                ->alignItems(AlignItems::CENTER);

            $emptyCard->add(
                UI::label('lbl_no_posts')
                    ->text('Aún no hay posts publicados.')
                    ->style('h3')
                    ->center()
            );

            $this->posts_list_container->add($emptyCard);
            return;
        }

        $isAdmin = $this->currentUserHasRole('admin');

        foreach ($posts as $post) {
            $postCard = UI::container("post_card_{$post->id}")
                ->card()
                ->layout(LayoutType::VERTICAL)
                ->minWidth(Size::px(175))
                ->flexGrow(1)
                ->padding(Spacing::px(20))
                ->gap(Spacing::px(12));

            if (!empty($post->image) && is_string($post->image)) {
                $imageUrl = $this->resolvePostImageUrl($post->image);

                // Componente UI::image() para mostrar la portada del post
                $postCard->add(
                    UI::image("post_img_{$post->id}")
                        ->src($imageUrl)
                        ->alt((string) $post->title)
                        ->width(Size::full())
                        ->height(Size::px(220))
                        ->objectFit('cover')
                        ->rounded(8)
                );
            }

            $postHeader = UI::container("post_header_{$post->id}")
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::SPACE_BETWEEN)
                ->alignItems(AlignItems::CENTER)
                ->plain()
                ->shadow(false)
                ->padding(Spacing::zero());

            $postHeader->add(
                UI::label("post_title_{$post->id}")
                    ->text((string) $post->title)
                    ->style('h3')
            );

            if ($isAdmin) {
                $postHeader->add(
                    UI::button("btn_delete_post_{$post->id}")
                        ->label('🗑️ Eliminar')
                        ->action('confirm_delete_post', ['post_id' => (int) $post->id])
                        ->style('danger')
                );
            }

            $postCard->add($postHeader);

            $authorName = (string) ($post->author_name ?? 'Autor');
            $createdAt = !empty($post->created_at) ? (string) $post->created_at : '';

            $postCard->add(
                UI::label("post_meta_{$post->id}")
                    ->text("Publicado por {$authorName}" . ($createdAt !== '' ? " • {$createdAt}" : ''))
                    ->style('caption')
                    ->color('#7a8499')
            );

            $postCard->add(
                UI::label("post_content_{$post->id}")
                    ->text((string) $post->content)
                    ->style('p')
            );

            $this->posts_list_container->add($postCard);
        }
    }

    private function resolvePostImageUrl(string $image): string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return "/files/uploads/images/{$image}";
    }

    /**
     * Muestra un diálogo modal de confirmación antes de borrar el post.
     */
    public function onConfirmDeletePost(array $params): void
    {
        if (!$this->currentUserHasRole('admin')) {
            $this->toast('No tienes permisos para eliminar posts.', 'error');
            return;
        }

        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: '¿Eliminar publicación?',
            message: '¿Estás seguro de que deseas eliminar este post? Esta acción no se puede deshacer.',
            confirmAction: 'delete_post',
            confirmParams: ['post_id' => $postId],
            cancelAction: 'cancel_delete_post',
            confirmLabel: 'Sí, eliminar',
            cancelLabel: 'Cancelar',
        );
    }

    public function onCancelDeletePost(array $params): void
    {
        $this->closeModal();
    }

    public function onDeletePost(array $params): void
    {
        $this->closeModal();

        if (!$this->currentUserHasRole('admin')) {
            $this->toast('No tienes permisos para eliminar posts.', 'error');
            return;
        }

        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        $deleted = $this->postService->deletePost($postId);

        if ($deleted) {
            $this->toast('Post eliminado correctamente.', 'success');
            $this->renderPostsList();
        } else {
            $this->toast('No se encontró el post a eliminar.', 'warning');
        }
    }

    /**
     * Maneja el evento reactivo de dominio cuando un usuario inicia sesión.
     * Actualiza el contenido de la pantalla Home con los permisos y roles del usuario autenticado.
     *
     * @param array<string, mixed> $params
     */
    public function onLoggedUser(array $params): void
    {
        $this->container->clear();
        $this->renderHomeContent($this->container);
    }

    /**
     * Maneja el evento reactivo de dominio cuando un usuario cierra sesión.
     * Restablece el contenido de la pantalla Home al modo invitado (guest).
     *
     * @param array<string, mixed> $params
     */
    public function onUserLoggedOut(array $params): void
    {
        $this->container->clear();
        $this->renderHomeContent($this->container);
    }
}
