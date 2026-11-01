<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Services\Post\ReelStoryboardFormatter;
use App\UI\Screens\Home;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

/**
 * Step 7: Resumen y publicación definitiva.
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 *
 * @method void goToStep(string $tabId)
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method static toast(string $message, string $type = 'info')
 * @method void navigate(string $screenClass, array<string, mixed> $params = [])
 */
trait HasSummaryAndPublishStep
{
    protected ?Button $btn_step7_back = null;

    protected ?Button $btn_publish_final = null;

    protected function buildSummaryAndPublishStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_summary_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(860))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $card->add(
            UI::label('lbl_summary_title')
                ->text('🚀 Resumen de Publicación y Confirmación')
                ->style('h3')
        );

        $card->add(
            UI::label('lbl_summary_intro')
                ->text('Revisa todos los elementos de la publicación antes de hacerla pública en el sistema y en las pantallas.')
                ->style('muted')
        );

        $card->add($this->buildSummaryContentBox($post));
        $card->add($this->buildStep7ActionsRow());

        return $card;
    }

    private function buildSummaryContentBox(?PostDTO $post): Container
    {
        $box = UI::container('box_summary_details')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(12));

        $title = $post !== null ? $post->title : 'Sin título';
        $content = $post !== null ? $post->content : 'Sin contenido';
        $from = $post !== null && $post->from !== null ? $post->from->format('Y-m-d') : 'Inmediata';
        $to = $post !== null && $post->to !== null ? $post->to->format('Y-m-d') : 'Indefinida';
        $reviewer = $post !== null && $post->reviewedBy !== null ? $post->reviewedBy->name : 'Revisor';

        $box->add(
            UI::label('lbl_summary_post_title')
                ->text("📌 **Título:** {$title}")
                ->markdown(true)
                ->style('h4')
        );

        $box->add(
            UI::label('lbl_summary_dates')
                ->text("🗓️ **Vigencia:** Desde {$from} hasta {$to}")
                ->markdown(true)
                ->style('info')
        );

        $box->add(
            UI::label('lbl_summary_reviewer')
                ->text("✅ **Aprobado por:** {$reviewer}")
                ->markdown(true)
                ->style('success')
        );

        $box->add(
            UI::label('lbl_summary_content')
                ->text("📝 **Notas/Texto:**\n{$content}")
                ->markdown(true)
                ->style('default')
        );

        if ($post !== null && ! empty($post->image)) {
            $imgBox = UI::container('box_summary_image')
                ->plain()
                ->width(Size::full())
                ->layout(LayoutType::VERTICAL)
                ->gap(Spacing::px(4));

            $imgBox->add(
                UI::label('lbl_summary_img_header')
                    ->text('🖼️ **Portada:**')
                    ->markdown(true)
            );

            $imgBox->add(
                UI::image('summary_cover_img')
                    ->src($post->image)
                    ->storage('uploads/images')
                    ->width(Size::px(320))
                    ->height(Size::px(180))
                    ->objectFit('contain')
            );

            $box->add($imgBox);
        }

        if ($post !== null && ! empty($post->aiScript)) {
            $storyboardMarkdown = app(ReelStoryboardFormatter::class)->format($post->aiScript);
            $box->add(
                UI::label('lbl_summary_storyboard')
                    ->text("🎬 **Guion del Reel:**\n{$storyboardMarkdown}")
                    ->markdown(true)
                    ->style('muted')
                    ->size('small')
            );
        }

        if ($post !== null && ! empty($post->reelVideo)) {
            $box->add(
                UI::label('lbl_summary_video_status')
                    ->text("📹 **Video Renderizado:** `/storage/reels/{$post->reelVideo}`")
                    ->markdown(true)
                    ->style('success')
            );
        }

        return $box;
    }

    private function buildStep7ActionsRow(): Container
    {
        $actions = UI::container('step7_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::zero());

        $this->btn_step7_back = UI::button('btn_step7_back')
            ->label('← Atrás: Generación de Video')
            ->action('step7_back')
            ->style('secondary');

        $this->btn_publish_final = UI::button('btn_publish_final')
            ->label('🚀 Confirmar y Publicar')
            ->action('publish_post_final')
            ->style('success');

        $actions->add($this->btn_step7_back);
        $actions->add($this->btn_publish_final);

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep7Back(array $params): void
    {
        $this->goToStep('step_video_generation_tab');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onPublishPostFinal(array $params): void
    {
        if ($this->state_post_id <= 0) {
            $this->toast('No se encontró el post a publicar.', 'error');

            return;
        }

        $post = $this->postService->findById($this->state_post_id);
        $statusVal = $post !== null ? (is_string($post->status) ? $post->status : $post->status->value) : '';

        if ($statusVal !== 'published') {
            $this->postService->updatePost($this->state_post_id, [
                'status' => 'published',
            ], Auth::user());
        }

        $this->toast('¡Publicación realizada con éxito!', 'success');
        $this->navigate(Home::class);
    }
}
