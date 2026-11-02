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
use App\Contracts\PostWorkflowStepInterface;

class ReviewerChat extends Screen implements PostWorkflowStepInterface
{
    protected NewLabel $label;

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(
            $this->label
                ->text("Reviewer Chat")
                ->style(Style::MUTED)
                ->size(TextSize::LARGE)
                ->center()
        );
    }

    public static function getName(): string
    {
        return "Reviewer Chat";
    }

    public function getPrevious(): ?string
    {
        return ReelStudio::class;
    }

    public function getNext(): ?string
    {
        return VideoStudio::class;
    }

    public function isNextEnabled(): bool
    {
        return false;
    }

    public function isPreviousEnabled(): bool
    {
        return true;
    }
}
