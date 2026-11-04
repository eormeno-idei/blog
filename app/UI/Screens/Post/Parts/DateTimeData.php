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

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $post = $this->resolveQueryPost();
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

    private function resolveQueryPost(): ?PostDTO
    {
        $queryParams = $this->getQueryParams();
        $postId = $queryParams['post_id'] ?? null;
        if ($postId === null) {
            return null;
        }

        return $this->postService->findById((int) $postId);
    }
}
