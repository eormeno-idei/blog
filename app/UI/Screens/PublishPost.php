<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class PublishPost extends Screen
{
    protected Input $post_title;
    protected Input $post_content;
    protected Uploader $post_image;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
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
        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(720))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

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
                ->text('✍️ Publicar Nuevo Post')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        $container->add($header);

        $formCard = UI::container('publish_form_card')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título atractivo...')
            ->required(true)
            ->width(Size::full());

        $this->post_content = UI::input('post_content')
            ->label('Contenido')
            ->placeholder('Desarrolla el contenido de tu publicación...')
            ->required(true)
            ->width(Size::full());

        // Componente Uploader para subir la imagen de portada
        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect('16:9')
            ->size(2)
            ->label('Imagen de Portada (opcional)');

        $actionsRow = UI::container('publish_actions_row')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(12))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $actionsRow->add(
            UI::button('btn_cancel_post')
                ->label('Cancelar')
                ->action('cancel_post')
                ->style('secondary')
        );

        $actionsRow->add(
            UI::button('btn_save_post')
                ->label('Publicar Post')
                ->action('save_post')
                ->style('primary')
        );

        $formCard->add($this->post_title);
        $formCard->add($this->post_content);
        $formCard->add($this->post_image);
        $formCard->add($actionsRow);

        $container->add($formCard);
    }

    public function onSavePost(array $params): void
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');
            return;
        }

        $rawTitle = $params['post_title'] ?? '';
        $rawContent = $params['post_content'] ?? '';

        $title = is_string($rawTitle) ? trim($rawTitle) : '';
        $content = is_string($rawContent) ? trim($rawContent) : '';

        $this->post_title->error(null);
        $this->post_content->error(null);

        $hasErrors = false;
        if ($title === '') {
            $this->post_title->error('El título es obligatorio.');
            $hasErrors = true;
        }

        if ($content === '') {
            $this->post_content->error('El contenido es obligatorio.');
            $hasErrors = true;
        }

        if ($hasErrors) {
            $this->toast('Por favor completa los campos obligatorios.', 'warning');
            return;
        }

        // Confirmar y mover la imagen temporal a storage/app/public/uploads/images/
        $confirmedImage = $this->post_image->confirm($params, 'images');
        $imageFilename = null;
        if (is_string($confirmedImage) && $confirmedImage !== '') {
            $imageFilename = $confirmedImage;
        } elseif (is_array($confirmedImage) && isset($confirmedImage[0]) && is_string($confirmedImage[0])) {
            $imageFilename = $confirmedImage[0];
        }

        $this->postService->createPost([
            'user_id' => (int) $user->id,
            'title' => $title,
            'content' => $content,
            'image' => $imageFilename,
        ]);

        $this->toast('¡Post publicado correctamente!', 'success');

        // Limpiar snapshot en caché de Home y navegar vía SPA forzando recarga de los últimos posts
        Screen::make(Home::class)->clearCachedScreenSnapshot();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    public function onCancelPost(array $params): void
    {
        Screen::make(Home::class)->clearCachedScreenSnapshot();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }
}

