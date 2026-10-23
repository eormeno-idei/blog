<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Http\Requests\PostRequest;
use App\Models\User;
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
use Illuminate\Support\Facades\Log;

class PublishPost extends Screen
{
    protected Input $post_title;
    protected Textarea $post_content;
    protected Uploader $post_image;

    public function __construct(
        protected PostServiceInterface $postService
    ) {}

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

        $this->post_content = UI::textarea('post_content')
            ->label('Notas rápidas')
            ->placeholder('Escribe algo aquí…')
            ->plainText()
            ->width(Size::full())
            ->height(Size::px(200))
            ->maxLength(300)
            ->helpText('Máximo 300 caracteres. Se guarda al salir del campo (onChange).')
            ->onChange('on_plain_saved');

        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect(AspectRatio::_16_9)
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

    public function onSavePost(PostRequest $request): void
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');
            return;
        }

        // Confirmar y persistir la imagen subida usando Uploader::confirm()
        $confirmedImage = $this->post_image->confirm($request->all(), 'images');
        $imageFilename = null;
        if (is_string($confirmedImage) && $confirmedImage !== '') {
            $imageFilename = $confirmedImage;
        } elseif (is_array($confirmedImage) && isset($confirmedImage[0]) && is_string($confirmedImage[0])) {
            $imageFilename = $confirmedImage[0];
        }

        $this->postService->createPost([
            'user_id' => (int) $user->id,
            'title' => $request->title(),
            'content' => $request->content(),
            'image' => $imageFilename,
            'background_color' => "#32637b",
            'text_color' => "#000000",
            'background_image' => null,
            'aspect_ratio' => '9:16',
            'from' => null,
            'to' => null,
            'status' => 'draft',
        ]);

        $this->toast('¡Post publicado correctamente!', 'success');

        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCancelPost(array $params): void
    {
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    public function onPlainSaved(array $params): void
    {
        $rawValue = $params['value'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $this->post_content->value($value);
    }
}
