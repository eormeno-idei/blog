<?php

namespace App\UI\Screens\Post\Parts;

use App\Contracts\PostWorkflowStepInterface;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\Style;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class DateTimeData extends Screen implements PostWorkflowStepInterface
{
    protected NewLabel $label;

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->add(
                $this->label
                    ->text("Date & Time")
                    ->style(Style::INFO)
                    ->size(TextSize::LARGE)
                    ->center()
            );
    }

    public static function getName(): string
    {
        return "Date & Time";
    }

    public function getPrevious(): ?string
    {
        return InitialData::class;
    }

    public function getNext(): ?string
    {
        return ReelStudio::class;
    }

    public function isNextEnabled(): bool
    {
        return false;
    }

    public function isPreviousEnabled(): bool
    {
        return false;
    }
}
