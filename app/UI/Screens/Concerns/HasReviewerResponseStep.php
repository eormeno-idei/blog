<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

/**
 * Step 5: Respuesta del revisor (Gestión de observaciones, rechazos y aprobación hacia el video).
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 *
 * @method void goToStep(string $tabId)
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method static toast(string $message, string $type = 'info')
 */
trait HasReviewerResponseStep
{
    protected ?Button $btn_return_to_draft = null;

    protected ?Button $btn_step5_next = null;

    protected ?Button $btn_step5_back = null;

    protected function buildReviewerResponseStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_reviewer_response_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(860))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $card->add(
            UI::label('lbl_reviewer_step_title')
                ->text('📬 Respuesta del Revisor')
                ->style('h3')
        );

        $statusValue = $post !== null ? (is_string($post->status) ? $post->status : $post->status->value) : 'draft';
        $comment = $post !== null ? ($post->reviewerComment ?? '') : '';
        $reviewerName = $post !== null && $post->reviewedBy !== null ? $post->reviewedBy->name : 'Revisor Institucional';

        if ($statusValue === 'changes_requested') {
            $card->add($this->buildRejectedAlert($reviewerName, $comment));
        } elseif ($statusValue === 'pending_review') {
            $card->add($this->buildPendingReviewBox());
        } else {
            $card->add($this->buildApprovedBox($reviewerName));
        }

        $card->add($this->buildStep5ActionsRow($statusValue));

        return $card;
    }

    private function buildRejectedAlert(string $reviewerName, string $comment): Container
    {
        $box = UI::container('box_rejected_feedback')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(10));

        $box->add(
            UI::label('lbl_rejected_title')
                ->text("⚠️ Modificaciones solicitadas por {$reviewerName}:")
                ->style('warning')
                ->bold()
        );

        $box->add(
            UI::label('lbl_rejected_body')
                ->text($comment !== '' ? $comment : 'Se han solicitado cambios en el contenido o guion antes de autorizar la publicación.')
                ->style('default')
        );

        $box->add(
            UI::label('lbl_rejected_instruction')
                ->text('Presiona el botón a continuación para regresar al Paso 1 en modo Borrador y aplicar los cambios requeridos.')
                ->style('muted')
                ->size('small')
        );

        return $box;
    }

    private function buildPendingReviewBox(): Container
    {
        $box = UI::container('box_pending_review')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(8));

        $box->add(
            UI::label('lbl_pending_title')
                ->text('⏳ Estado: Pendiente de Revisión')
                ->style('info')
                ->bold()
        );

        $box->add(
            UI::label('lbl_pending_body')
                ->text('Tu propuesta fue enviada. En cuanto el revisor emita su respuesta, podrás avanzar a la generación del video o ver las observaciones solicitadas.')
                ->style('muted')
        );

        return $box;
    }

    private function buildApprovedBox(string $reviewerName): Container
    {
        $box = UI::container('box_approved_review')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(8));

        $box->add(
            UI::label('lbl_approved_title')
                ->text('✅ ¡Propuesta Aprobada!')
                ->style('success')
                ->bold()
        );

        $box->add(
            UI::label('lbl_approved_body')
                ->text("El revisor ({$reviewerName}) ha aprobado tu propuesta de contenido y guion de Reel. Ya puedes generar el video final en alta resolución.")
                ->style('default')
        );

        return $box;
    }

    private function buildStep5ActionsRow(string $statusValue): Container
    {
        $actions = UI::container('step5_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->padding(Spacing::zero());

        $this->btn_step5_back = UI::button('btn_step5_back')
            ->label('← Atrás: Enviar a Revisión')
            ->action('step5_back')
            ->style('secondary');

        $actions->add($this->btn_step5_back);

        if ($statusValue === 'changes_requested') {
            $this->btn_return_to_draft = UI::button('btn_return_to_draft')
                ->label('✏️ Volver al Paso 1 como Borrador para Corregir')
                ->action('return_to_draft')
                ->style('warning');

            $actions->add($this->btn_return_to_draft);
        } else {
            $canProceed = in_array($statusValue, ['published', 'reel_generated', 'reel_generation_pending', 'ia_generated', 'ready_for_review'], true)
                || $statusValue !== 'pending_review';

            $this->btn_step5_next = UI::button('btn_step5_next')
                ->label('Siguiente: Generación de Video →')
                ->action('step5_next')
                ->style('primary')
                ->enabled($canProceed);

            $actions->add($this->btn_step5_next);
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep5Back(array $params): void
    {
        $this->goToStep('step_review_submission_tab');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onReturnToDraft(array $params): void
    {
        if ($this->state_post_id > 0) {
            $this->postService->updatePost($this->state_post_id, [
                'status' => 'draft',
            ], Auth::user());
        }

        $this->toast('El post volvió a estado de borrador para su corrección.', 'info');
        $this->goToStep('form_tab');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStep5Next(array $params): void
    {
        $this->enableStepTab('step_video_generation_tab', activate: true);
    }
}
