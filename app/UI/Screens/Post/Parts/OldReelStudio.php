<?php

namespace App\UI\Screens\Post\Parts;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\Style;
use Idei\Usim\Screen;

class OldReelStudio extends Screen
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
                    ->text("Reel Studio")
                    ->style(Style::INFO)
                    ->size(TextSize::LARGE)
                    ->center()
            );
    }
}
