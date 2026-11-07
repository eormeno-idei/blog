<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\UI\Requests\PostRequest;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class DateTimeData extends Screen
{
    protected ?Input $post_from;
    protected ?Input $post_to;
    protected ?Button $confirm_dates;

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
        $card->add(
            $this->confirm_dates = UI::button('confirm_dates')
                ->label('Confirmar fechas')
                ->action('on_confirm_dates')
                ->width(Size::full())
        );

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

        $fromVal = $post->from ? $post->from->isoFormat('YYYY-MM-DD') : null;
        $toVal = $post->to ? $post->to->isoFormat('YYYY-MM-DD') : null;

        $this->post_from = UI::input('post_from')
            ->type('date')
            ->label('Fecha desde la que debe publicarse')
            ->value($fromVal)
            ->required()
            ->width(Size::full());

        $this->post_to = UI::input('post_to')
            ->type('date')
            ->label('Fecha hasta la que debe publicarse')
            ->value($toVal)
            ->required()
            ->width(Size::full());

        $row->add($this->post_from);
        $row->add($this->post_to);

        return $row;
    }

    public function onConfirmDates(int $post_id, PostRequest $postRequest): void
    {
        $post_from = $postRequest->from;
        $post_to = $postRequest->to;

        if ($post_from && $post_to && $post_from > $post_to) {
            $this->toast('La fecha "desde" no puede ser mayor que la fecha "hasta".', 'error');
        }

        $this->postService->updatePost($post_id, [
            'from' => $post_from,
            'to' => $post_to,
        ]);

        event(new UsimEvent('post_changed', [
            'postId' => $post_id,
            'stepIndex' => 1,
        ]));
    }
}
