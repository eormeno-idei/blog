<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

/**
 * Step 2: Imagen de portada (Uploader opcional con crop 16:9).
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 *
 * @method void goToStep(string $tabId)
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method int resolveCandidatePostId(array<string, mixed> $params = [])
 * @method static toast(string $message, string $type = 'info')
 */
trait HasPostImageStep
{
    protected Uploader $post_image;

    protected Button $btn_step2_back;

    protected Button $btn_step2_next;

    protected function buildImageStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_image_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(860))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $card->add(
            UI::label('lbl_step_image_title')
                ->text('🖼️ Imagen de Portada')
                ->style('h4')
        );

        $card->add(
            UI::label('lbl_step_image_help')
                ->text('Sube una imagen de portada para la publicación (opcional). Formato recomendado 16:9.')
                ->style('muted')
                ->size('small')
        );

        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect(AspectRatio::_16_9)
            ->size(2)
            ->label('Subir imagen');

        if ($post !== null && ! empty($post->image)) {
            $this->post_image->existingFile("/storage/uploads/images/{$post->image}");
        }

        $card->add($this->post_image);
        $card->add($this->buildStep2ActionsRow());

        return $card;
    }

    private function buildStep2ActionsRow(): Container
    {
        $actions = UI::container('step2_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->padding(Spacing::zero());

        $this->btn_step2_back = UI::button('btn_step2_back')
            ->label('← Atrás: Datos')
            ->action('step2_back')
            ->style('secondary');

        $this->btn_step2_next = UI::button('btn_step2_next')
            ->label('Siguiente: Estructurar Reel con IA →')
            ->action('step2_next')
            ->style('primary');

        $actions->add($this->btn_step2_back);
        $actions->add($this->btn_step2_next);

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep2Back(array $params): void
    {
        $this->goToStep('form_tab');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep2Next(array $params): void
    {
        $imageFilename = $this->resolveUploadedImageFilename($params);

        if ($this->state_post_id > 0 && $imageFilename !== null) {
            $this->postService->updatePost($this->state_post_id, [
                'image' => $imageFilename,
            ], Auth::user());
        }

        $this->enableStepTab('reel_studio_tab', activate: true);
    }

    /**
     * @param  array<string, mixed>  $requestData
     */
    private function resolveUploadedImageFilename(array $requestData): ?string
    {
        if (! isset($this->post_image)) {
            return null;
        }

        $confirmedImage = $this->post_image->confirm($requestData, 'images');

        if (is_string($confirmedImage) && $confirmedImage !== '') {
            return $confirmedImage;
        }

        if (is_array($confirmedImage)) {
            $first = reset($confirmedImage);

            return is_string($first) && $first !== '' ? $first : null;
        }

        return null;
    }
}
