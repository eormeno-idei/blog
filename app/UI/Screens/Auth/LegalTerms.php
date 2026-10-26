<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Auth;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Str;

class LegalTerms extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container()
            ->card()
            ->width(Size::px(800))
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER);

        $card = UI::container()
            ->width(Size::full());

        $card->add(
            UI::label()
                ->html($this->termsText())
                ->inline(false)
                ->width(Size::full())
        );

        $buttons = UI::container()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->plain()
            ->shadow(false)
            ->gap(Spacing::px(5))
            ->padding(Spacing::zero());

        $buttons->add(
            UI::button()
                ->label(t('screens.auth.legal_terms.close'))
                ->style('secondary')
                ->action('close_legal_terms')
        );

        // $card->add($buttons);
        $wrapper->add($buttons)->add($card);
        $container->add($wrapper);
    }

    private function termsText(): string
    {
        $document = t('screens.auth.legal_terms.document');
        $markdownHtml = Str::markdown($document);
        $scrollableDocumentHtml = "<div class='usim-markdown-content'>$markdownHtml</div>";

        return $scrollableDocumentHtml;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCloseLegalTerms(array $params = []): void
    {
        $this->closeModal();
    }
}
