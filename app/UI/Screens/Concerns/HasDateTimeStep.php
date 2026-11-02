<?php

declare(strict_types=1);

namespace App\UI\Screens\Concerns;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Step 2: Fechas de inicio y finalización
 *
 * @mixin PublishPost
 *
 * @property int $state_post_id
 * @property PostServiceInterface $postService
 *
 * @method void enableStepTab(string $tabId, bool $activate = false)
 * @method int resolveCandidatePostId(array<string, mixed> $params = [])
 * @method static toast(string $message, string $type = 'info')
 */
trait HasDateTimeStep
{
    protected Input $post_id;

    protected Input $post_from;

    protected Input $post_to;

    protected Button $btn_step1_next;

    protected function buildDateTimeStepCard(?PostDTO $post = null): Container
    {
        $card = UI::container('step_data_card')
            ->card()
            ->width(Size::full())
            ->maxWidth(Size::px(860))
            ->centerHorizontal()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $this->post_id = UI::input('post_id')
            ->type('hidden')
            ->value((string) $this->state_post_id);

        $card->add($this->post_id);
        $card->add($this->buildDateRangeRow($post));
        $card->add($this->buildDateTimeActionsRow());

        return $card;
    }

    private function buildDateRangeRow(?PostDTO $post = null): Container
    {
        $row = UI::container('publish_dates_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(16))
            ->padding(Spacing::zero());

        $fromVal = $post !== null && $post->from !== null
            ? $post->from->format('Y-m-d')
            : now()->format('Y-m-d');

        $toVal = $post !== null && $post->to !== null
            ? $post->to->format('Y-m-d')
            : now()->addDays(14)->format('Y-m-d');

        $this->post_from = UI::input('post_from')
            ->type('date')
            ->label('Fecha desde la que debe publicarse')
            ->value($fromVal)
            ->width(Size::full());

        $this->post_to = UI::input('post_to')
            ->type('date')
            ->label('Fecha hasta la que debe publicarse')
            ->value($toVal)
            ->width(Size::full());

        $row->add($this->post_from);
        $row->add($this->post_to);

        return $row;
    }

    private function buildDateTimeActionsRow(): Container
    {
        $actions = UI::container('step1_actions_row')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->padding(Spacing::zero());

        $this->btn_step1_next = UI::button('btn_step1_next')
            ->label('Siguiente: Imagen de Portada →')
            ->action('step1_next')
            ->style('primary');

        $actions->add($this->btn_step1_next);

        return $actions;
    }
}
