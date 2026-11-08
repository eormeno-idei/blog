<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait HasFirstDraftPanel
{
    protected Container $context_received_card;
    protected Textarea $initial_instructions;
    protected Button $btn_generate_first_structure;

    protected function buildFirstDraftPanel(): Container
    {
        $leftCol = UI::container('first_draft_panel')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::px(320))
            ->maxWidth(Size::px(320))
            ->gap(Spacing::px(14))
            ->padding(Spacing::zero());

        $this->initial_instructions = UI::textarea('initial_instructions')
            ->placeholder('Agrega instrucciones adicionales para el primer borrador (Opcional)')
            ->width(Size::full());

        $this->btn_generate_first_structure = UI::button('btn_generate_first_structure')
            ->label('Generar Primera Estructura')
            ->icon('✨')
            ->style('primary')
            ->fullWidth(true)
            ->action('generate_first_structure');

        $leftCol->add($this->contextReceivedCard());
        $leftCol->add($this->initial_instructions);
        $leftCol->add($this->btn_generate_first_structure);

        return $leftCol;
    }

    private function contextReceivedCard(): Container
    {
        return $this->context_received_card
            ->card()
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->padding(Spacing::px(5))
            ->gap(Spacing::px(5))
            ->justifyContent(JustifyContent::START)
            ->add(
                UI::newLabel('context_received_title')
                    ->text('✅ Contexto recibido')
            )->add(
                UI::newLabel('context_received_content')
                    ->text('✅ Texto Markdown cargado')
            )->add(
                UI::newLabel('context_received_images')
                    ->text('✅ Imágenes adjuntas')
            );
    }
}
