<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Home extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected ?Container $posts_list_container = null;
    protected Label $lbl_registered_message;

    public function __construct(
        protected PostServiceInterface $postService
    ) {}

    public function buildBaseUI(Container $container, ...$params): void
    {
        /** @var User $user */
        $user = Auth::user();

        $wrapper = UI::container('home_wrapper')
            ->centerHorizontal()
            ->plain();

        // Encabezado con título y acciones según el rol activo
        $header = UI::container('home_header')
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $titleBox = UI::container('home_title_box')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $titleBox->add(
            $this->lbl_registered_message
                ->text('')
                ->style('h4')
        );

        // Si el usuario tiene únicamente el rol "registered"
        if ($user instanceof User && $user->isRegisteredInLobby()) {
            $this->lbl_registered_message
                ->text("{$user->name}, aún estás en sala de espera.");
        }

        $titleBox->add(
            UI::label('lbl_home_title')
                ->text('📰 Últimos Posts')
                ->style('h2')
        );

        $subtitleText = $this->resolveRoleSubtitle($user);
        $titleBox->add(
            UI::label('lbl_home_subtitle')
                ->text($subtitleText)
                ->style('p')
        );

        $actionsBox = UI::container('home_actions_box')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(10))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        if (!$user instanceof User) {
            $actionsBox->add(
                UI::button('btn_home_login')
                    ->label('🔑 Iniciar Sesión')
                    ->action('open_login')
                    ->style('primary')
            );
            $actionsBox->add(
                UI::button('btn_home_register')
                    ->label('📝 Registrarse')
                    ->action('open_register')
                    ->style('secondary')
            );
        } else {
            if ($this->currentUserHasRole(['publisher', 'admin'])) {
                $actionsBox->add(
                    UI::button('btn_go_publish')
                        ->label('✍️ Publicar Post')
                        ->action('go_to_publish')
                        ->style('primary')
                );
            }

            if ($this->currentUserHasRole('admin')) {
                $actionsBox->add(
                    UI::button('btn_go_users')
                        ->label('👥 Gestionar Usuarios')
                        ->action('go_to_users')
                        ->style('secondary')
                );
            }
        }

        $header->add($titleBox);
        $header->add($actionsBox);
        $wrapper->add($header);

        // Contenedor reactivo para el listado de posts
        $this->posts_list_container = UI::container('posts_list_container')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(16));

        $this->renderPostsList();

        $wrapper->add($this->posts_list_container);

        $container->add($wrapper);
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

    private function resolveRoleSubtitle(?User $user): string
    {
        if ($user === null) {
            return 'Modo invitado (guest): visualiza las publicaciones recientes o inicia sesión para participar.';
        }

        if ($this->currentUserHasRole('admin')) {
            return "Conectado como {$user->name} (Administrador): puedes publicar posts, eliminarlos y asignar roles a usuarios registrados.";
        }

        if ($this->currentUserHasRole('publisher')) {
            return "Conectado como {$user->name} (Publicador): puedes compartir nuevos posts con la comunidad.";
        }

        return "Conectado como {$user->name}.";
    }

    private function renderPostsList(): void
    {
        $this->posts_list_container->clear();

        $posts = $this->postService->getLatestPosts();

        if ($posts->isEmpty()) {
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

            $this->posts_list_container->add($emptyCard);
            return;
        }

        $isAdmin = $this->currentUserHasRole('admin');

        $dim = AspectRatio::_16_9->dimensionsFor(width: 480);
        $height = Size::px($dim[1] + 40);

        foreach ($posts as $post) {
            $postCard = UI::container("post_card_{$post->id}")
                ->card()
                ->layout(LayoutType::VERTICAL)
                ->minWidth(Size::px(300))->maxWidth(Size::px(300))
                ->minHeight(Size::px(350))->maxHeight(Size::px(350))
                ->padding(Spacing::px(4))
                ->gap(Spacing::px(4));

            $postCard->add(
                UI::image("post_img_{$post->id}")
                    ->src($post->image ?? '')
                    ->storage('uploads/images')
                    ->caption((string) $post->title)
                    ->width(Size::full())
                    ->height($height)
                    ->objectFit('scale-down')
                    ->rounded(4)
                    ->placeholderAspect(AspectRatio::_16_9)
                    ->placeholderId($post->id)
            );

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

            $postCard->add($postHeader);

            $authorName = (string) ($post->author_name ?? 'Autor');
            $createdAt = !empty($post->created_at) ? (string) $post->created_at : '';

            $postCard->add(
                UI::label("post_meta_{$post->id}")
                    ->text("{$authorName} • {$createdAt}")
            );

            $postCard->add(
                UI::label("post_content_{$post->id}")
                    ->text((string) $post->content)
                    ->style('p')
            );

            $postActionBox = UI::container("post_action_box_{$post->id}")
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::END)
                ->plain()
                ->shadow(false)
                ->padding(Spacing::zero());

            if ($isAdmin) {
                $postActionBox->add(
                    UI::button("btn_delete_post_{$post->id}")
                        ->label('🗑️')
                        ->action('delete_post', ['post_id' => (int) $post->id])
                        ->style('danger')
                );
            }

            $postCard->add($postActionBox);

            $this->posts_list_container->add($postCard);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onOpenLogin(array $params): void
    {
        $this->openModal(Login::class);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onOpenRegister(array $params): void
    {
        $this->openModal(Register::class);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onGoToPublish(array $params): void
    {
        $this->showInto(PublishPost::class, updateBrowserUrl: true, force: true);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onGoToUsers(array $params): void
    {
        $this->showInto(UsersManager::class, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $params
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onDeletePost(array $params): void
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

        $deleted = $this->postService->deletePost($postId);

        if ($deleted) {
            $this->toast('Post eliminado correctamente.', 'success');

            // 1. Buscar y remover puntualmente la tarjeta del post eliminado
            if ($this->posts_list_container !== null) {
                $postCard = $this->posts_list_container->findByName("post_card_{$postId}");
                if ($postCard !== null) {
                    $this->posts_list_container->tryRemove($postCard->getId());
                }

                // 2. Si no quedan posts, mostrar la tarjeta de lista vacía
                if ($this->posts_list_container->count() === 0) {
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

                    $this->posts_list_container->add($emptyCard);
                }
            }
        } else {
            $this->toast('No se encontró el post a eliminar.', 'warning');
        }
    }

    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        $this->lbl_registered_message->text("{$user->name}, aún estás en sala de espera.");
    }
}
