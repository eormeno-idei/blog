<?php

namespace App\UI\Screens\Post\Parts;

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

class SumaryPublish extends Screen
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
                    ->text("Summary & Publish")
                    ->style(Style::SUCCESS)
                    ->size(TextSize::LARGE)
                    ->center()
            );
    }
}
