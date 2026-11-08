<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait HasEditPanel
{
    // Mode switcher buttons
    protected Button $btn_edit_scene;
    protected Button $btn_edit_all_reel;

    // Prompt area
    protected Textarea $edit_prompt_input;
    protected Button $btn_submit_prompt;

    // Quick actions
    protected NewLabel $lbl_quick_actions;
    protected Button $btn_summarize_text;
    protected Button $btn_expand_details;
    protected Button $btn_reduce_time;
    protected Button $btn_increase_time;

    // Footer CTA
    protected Button $btn_continue_to_design;

    protected function buildEditReelPanel(): Container
    {
        $editPanel = UI::container('edit_panel')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::px(320))
            ->maxWidth(Size::px(320))
            ->gap(Spacing::px(16))
            ->padding(Spacing::zero())
            ->visible(false);

        // 1. Selector superior tipo pestañas/toggle (Grid de 2 columnas)
        $modeSwitchContainer = UI::container('mode_switch_container')
            ->layout(LayoutType::GRID)
            ->gridColumns(2)
            ->gap(Spacing::px(8))
            ->width(Size::full());

        $this->btn_edit_scene = UI::button('btn_edit_scene')
            ->label('Editar Escena')
            ->icon('✏️')
            ->style('primary')
            ->shape('pill')
            ->fullWidth(true)
            ->action('set_edit_mode', ['mode' => 'scene']);

        $this->btn_edit_all_reel = UI::button('btn_edit_all_reel')
            ->label('Editar todo el Reel')
            ->icon('⊞')
            ->style('default')
            ->variant('ghost')
            ->shape('pill')
            ->fullWidth(true)
            ->action('set_edit_mode', ['mode' => 'reel']);

        $modeSwitchContainer->add($this->btn_edit_scene);
        $modeSwitchContainer->add($this->btn_edit_all_reel);

        // 2. Área de texto con botón de acción IA flotante en la esquina inferior derecha
        $promptBoxContainer = UI::container('prompt_box_container')
            ->position('relative')
            ->width(Size::full());

        $this->edit_prompt_input = UI::textarea('edit_prompt_input')
            ->placeholder('Ej: Cambia el título por algo más llamativo...')
            ->height(Size::px(160))
            ->width(Size::full())
            ->borderRadius(12);

        $this->btn_submit_prompt = UI::button('btn_submit_prompt')
            ->icon('✨')
            ->iconOnly(true)
            ->style('primary')
            ->shape('rounded')
            ->position('BOTTOM_RIGHT')
            ->positionMode('absolute')
            ->offsets(12, 12)
            ->action('apply_edit_prompt');

        $promptBoxContainer->add($this->edit_prompt_input);
        $promptBoxContainer->add($this->btn_submit_prompt);

        // 3. Título de sección: Acciones Rápidas
        $this->lbl_quick_actions = UI::newLabel('lbl_quick_actions')
            ->text('Acciones Rápidas')
            ->bold()
            ->large();

        // 4. Grid de Acciones Rápidas (Grid de 2 columnas x 2 filas)
        $quickActionsGrid = UI::container('quick_actions_grid')
            ->layout(LayoutType::GRID)
            ->gridColumns(2)
            ->gap(Spacing::px(10))
            ->width(Size::full());

        $this->btn_summarize_text = UI::button('btn_summarize_text')
            ->label('Resumir texto')
            ->icon('✏️')
            ->variant('outline')
            ->shape('rounded')
            ->fullWidth(true)
            ->action('quick_action_summarize');

        $this->btn_expand_details = UI::button('btn_expand_details')
            ->label('Ampliar detalles')
            ->icon('➕')
            ->variant('outline')
            ->shape('rounded')
            ->fullWidth(true)
            ->action('quick_action_expand');

        $this->btn_reduce_time = UI::button('btn_reduce_time')
            ->label('Reducir tiempo')
            ->icon('⏱️')
            ->variant('outline')
            ->shape('rounded')
            ->fullWidth(true)
            ->action('quick_action_reduce_time');

        $this->btn_increase_time = UI::button('btn_increase_time')
            ->label('Aumentar tiempo')
            ->icon('⏳')
            ->variant('outline')
            ->shape('rounded')
            ->fullWidth(true)
            ->action('quick_action_increase_time');

        $quickActionsGrid->add($this->btn_summarize_text);
        $quickActionsGrid->add($this->btn_expand_details);
        $quickActionsGrid->add($this->btn_reduce_time);
        $quickActionsGrid->add($this->btn_increase_time);

        // 5. Botón principal inferior (Call to Action)
        $this->btn_continue_to_design = UI::button('btn_continue_to_design')
            ->label('Continuar a Diseño y Animación ➔')
            ->style('primary')
            ->shape('rounded')
            ->size('medium')
            ->fullWidth(true)
            ->action('continue_to_design');

        // Ensamblado del panel
        $editPanel->add($modeSwitchContainer);
        $editPanel->add($promptBoxContainer);
        $editPanel->add($this->lbl_quick_actions);
        $editPanel->add($quickActionsGrid);
        $editPanel->add($this->btn_continue_to_design);

        return $editPanel;
    }
}
