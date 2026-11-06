<?php

namespace App\UI\Screens\Post\Parts;

use Idei\Usim\Screen;
use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Events\UsimEvent;

class DateTimeData extends Screen
{
    protected ?Input $post_from;
    protected ?Input $post_to;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
        parent::__construct();
    }

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    protected function buildBaseUI(Container $container, int $post_id): void
    {
        $post = $this->postService->findById($post_id);

        $container
            ->width(Size::full())
            ->height(Size::full())
            ->justifyContent(JustifyContent::START)
            ->add(
                $this->buildDateTimeStepCard($post)
            );
    }

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

        $card->add($this->buildDateRangeRow($post));

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

        $fromVal = $post->from->isoFormat('YYYY-MM-DD');
        $toVal = $post->to->isoFormat('YYYY-MM-DD');

        $this->post_from = UI::input('post_from')
            ->type('date')
            ->label('Fecha desde la que debe publicarse')
            ->value($fromVal)
            ->required()
            ->onChange('on_from_changed')
            ->width(Size::full());

        $this->post_to = UI::input('post_to')
            ->type('date')
            ->label('Fecha hasta la que debe publicarse')
            ->value($toVal)
            ->required()
            ->onChange('on_to_changed')
            ->width(Size::full());

        $row->add($this->post_from);
        $row->add($this->post_to);

        return $row;
    }

    public function onFromChanged(int $post_id, string $post_from): void
    {
        $post = $this->postService->findById($post_id);
        if ($post_from > $post->to->isoFormat('YYYY-MM-DD')) {
            $this->toast('La fecha "desde" no puede ser mayor que la fecha "hasta".', 'error');
            $this->post_from->value($post->from->isoFormat('YYYY-MM-DD'));
            return;
        }
        $this->postService->updatePost($post_id, [
            'from' => $post_from,
        ]);
        event(new UsimEvent('post_changed', [
            'postId' => $post_id,
            'stepIndex' => 1,
        ]));
    }

    public function onToChanged(int $post_id, string $post_to): void
    {
        $post = $this->postService->findById($post_id);
        if ($post_to < $post->from->isoFormat('YYYY-MM-DD')) {
            $this->toast('La fecha "hasta" no puede ser menor que la fecha "desde".', 'error');
            $this->post_to->value($post->to->isoFormat('YYYY-MM-DD'));
            return;
        }
        $this->postService->updatePost($post_id, [
            'to' => $post_to,
        ]);
        event(new UsimEvent('post_changed', [
            'postId' => $post_id,
            'stepIndex' => 1,
        ]));
    }
}
