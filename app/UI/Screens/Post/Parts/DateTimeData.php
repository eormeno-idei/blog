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

        $fromVal = $post->from !== null
            ? $post->from->isoFormat('YYYY-MM-DD')
            : now()->addDays(2)->isoFormat('YYYY-MM-DD');

        $toVal = $post !== null && $post->to !== null
            ? $post->to->isoFormat('YYYY-MM-DD')
            : now()->addDays(14)->isoFormat('YYYY-MM-DD');

        $this->post_from = UI::input('post_from')
            ->type('date')
            ->label('Fecha desde la que debe publicarse')
            ->value($fromVal)
            ->required()
            ->onChange('on_post_from_change')
            ->width(Size::full());

        $this->post_to = UI::input('post_to')
            ->type('date')
            ->label('Fecha hasta la que debe publicarse')
            ->value($toVal)
            ->required()
            ->onChange('on_post_to_change')
            ->width(Size::full());

        $row->add($this->post_from);
        $row->add($this->post_to);

        return $row;
    }

    public function onPostFromChange(int $post_id, string $newValue): void
    {
        $this->postService->updatePost($post_id, [
            'from' => $newValue,
        ]);
    }

    public function onPostToChange(int $post_id, string $newValue): void
    {
        $this->postService->updatePost($post_id, [
            'to' => $newValue,
        ]);
    }
}
