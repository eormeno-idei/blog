<?php

namespace App\UI\Screens\Post\Parts\ReelStudioConcerns;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Textarea;
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

    /**
     * Configure the edit panel and attach its declarative 2-column grid layout.
     */
    protected function buildEditReelPanel(): Container
    {
        // 1. Selector superior tipo pestañas/toggle
        $this->btn_edit_scene
            ->label('Editar Escena')
            ->icon('✏️')
            ->style('primary')
            ->shape('pill')
            ->action('set_edit_mode', ['mode' => 'scene']);

        $this->btn_edit_all_reel
            ->label('Editar todo el Reel')
            ->icon('⊞')
            ->style('default')
            ->variant('ghost')
            ->shape('pill')
            ->action('set_edit_mode', ['mode' => 'reel']);

        // 2. Área de texto con botón de acción IA superpuesto en la celda [2, 2]
        $this->edit_prompt_input
            ->placeholder('Ej: Cambia el título por algo más llamativo...')
            ->borderRadius(12);

        // ContainerLayoutLoader forwards child `margin` without converting scalars to Spacing.
        $this->btn_submit_prompt
            ->icon('✨')
            ->style('primary')
            ->shape('rounded')
            ->marginRight(Spacing::px(12))
            ->marginBottom(Spacing::px(34))
            ->zIndex(2)
            ->action('apply_edit_prompt');

        // 3. Título de sección: Acciones Rápidas
        $this->lbl_quick_actions
            ->text('Acciones Rápidas')
            ->bold()
            ->large();

        // 4. Acciones rápidas (2x2 en el grid del panel)
        $quickActions = [
            [$this->btn_summarize_text, 'Resumir texto', 'edit', 'quick_action_summarize'],
            [$this->btn_expand_details, 'Ampliar detalles', 'plus', 'quick_action_expand'],
            [$this->btn_reduce_time, 'Reducir tiempo', 'clock', 'quick_action_reduce_time'],
            [$this->btn_increase_time, 'Aumentar tiempo', 'clock', 'quick_action_increase_time'],
        ];

        foreach ($quickActions as [$button, $label, $icon, $action]) {
            $button
                ->label($label)
                ->icon($icon)
                ->variant('outline')
                ->shape('rounded')
                ->action($action);
        }

        // 5. Botón principal inferior (Call to Action)
        $this->btn_continue_to_design
            ->label('Continuar a Diseño y Animación ➔')
            ->style('primary')
            ->shape('rounded')
            ->size('medium')
            ->action('continue_to_design');

        return $this->edit_panel
            ->layoutFile('reel-studio-edit.layout.yaml')
            ->visible(false);
    }
}
