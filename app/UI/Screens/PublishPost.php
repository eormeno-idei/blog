<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\WorkflowStateDTO;
use App\Services\Post\PostWorkflowService;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class PublishPost extends Screen
{
    protected Container $buttons_container;
    protected Container $demo_container;
    protected Button $btn_previous_step;
    protected Button $btn_next_step;

    protected int $state_post_id = 0;
    protected int $state_currentStepIndex = 0;

    public function __construct(
        protected PostServiceInterface $postService,
        protected PostWorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function buildBaseUI(Container $container, int $postId): void
    {
        $container->padding(Spacing::zero());
        $stepIndex = $this->resolveCurrentStepIndex();
        $workflow = $this->workflowService->getWorkflowState($postId, $stepIndex);

        $this->demo_container = UI::container('demo_container')
            ->card()
            ->shadow(false)
            ->width(Size::full())
            ->height(Size::px(500));

        $wrapper = UI::container('wrapper')
            ->width(Size::full())
            ->padding(Spacing::zero())
            ->gap(Spacing::px(4));

        $wrapper->add($this->buildStepsBar($workflow));
        $wrapper->add($this->demo_container);
        $wrapper->add($this->buildNavigation($workflow));

        $container->add($this->buildHeader());
        $container->add($wrapper);
    }

    protected function postLoadUI(int $postId): void
    {
        $workflow = $this->workflowService->getWorkflowState($postId, 0);
        $this->demo_container->embed($workflow->currentStep()->screenClass);
    }

    public function onGoToNextStep(int $postId): void
    {
        $stepIndex = $this->resolveCurrentStepIndex();
        $workflow = $this->workflowService->getWorkflowState($postId, $stepIndex);

        if (!$workflow->canAdvance) {
            $this->toast('No puedes avanzar al siguiente paso. Asegúrate de completar los requisitos del paso actual.', 'warning');
            // Regla de negocio no cumplida: mostrar feedback visual sin avanzar
            return;
        }

        $this->state_currentStepIndex++;
        $newWorkflow = $this->workflowService->getWorkflowState($postId, $this->state_currentStepIndex);

        $this->demo_container->embed($newWorkflow->currentStep()->screenClass);
        $this->refreshNavigationState($newWorkflow);
    }

    public function onGoToPreviousStep(int $postId): void
    {
        $this->state_currentStepIndex--;
        $newWorkflow = $this->workflowService->getWorkflowState($postId, $this->state_currentStepIndex);

        $this->demo_container->embed($newWorkflow->currentStep()->screenClass);
        $this->refreshNavigationState($newWorkflow);
    }

    private function resolveCurrentStepIndex(): int
    {
        $rawIndex = $this->getQueryParams()['step_index'] ?? null;
        if (is_numeric($rawIndex) && (int) $rawIndex >= 0) {
            $this->state_currentStepIndex = (int) $rawIndex;
        }
        return max(0, $this->state_currentStepIndex);
    }

    private function buildStepsBar(WorkflowStateDTO $workflow): Container
    {
        $this->buttons_container = UI::container('buttons_container')
            ->layout(LayoutType::HORIZONTAL)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(10));

        foreach ($workflow->steps as $index => $step) {
            $index_inc = $index + 1;
            $style = $step->isCurrent ? 'primary' : 'secondary';

            $button = UI::button("step_btn_{$index}")
                ->label("{$index_inc}. {$step->label}")
                ->status($step->isCurrent)
                ->enabled($step->canAccess)
                ->style($style);

            if ($step->canAccess) {
                $button->action('on_select_step', ['index' => $index]);
            }

            $this->buttons_container->add($button);
        }

        return $this->buttons_container;
    }

    private function updateStepsBar(WorkflowStateDTO $workflow): void
    {
        foreach ($this->buttons_container->getChildren() as $index => $btn) {
            if ($btn instanceof Button && isset($workflow->steps[$index])) {
                $step = $workflow->steps[$index];
                $btn->status($step->isCurrent);
                $btn->enabled($step->canAccess);
                $btn->style($step->isCurrent ? 'primary' : 'secondary');
            }
        }
    }

    public function onPostChanged(int $postId, int $stepIndex): void
    {
        $workflow = $this->workflowService->getWorkflowState($postId, $stepIndex);
        $this->updateStepsBar($workflow);
    }

    private function buildNavigation(WorkflowStateDTO $workflow): Container
    {
        $nav = UI::container('navigation')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN);

        $this->btn_previous_step = UI::button('btn_previous_step')
            ->label('← Paso Anterior')
            ->action('go_to_previous_step')
            ->enabled($workflow->canGoBack)
            ->status($workflow->canGoBack);

        $this->btn_next_step = UI::button('btn_next_step')
            ->label('Siguiente Paso →')
            ->action('go_to_next_step')
            ->enabled($workflow->canAdvance)
            ->status($workflow->canAdvance);

        $nav->add($this->btn_previous_step);
        $nav->add($this->btn_next_step);

        return $nav;
    }

    private function refreshNavigationState(WorkflowStateDTO $workflow): void
    {
        $this->btn_previous_step->status($workflow->canGoBack);
        $this->btn_next_step->status($workflow->canAdvance);
        // Actualizar estilos de los botones del stepper
        foreach ($this->buttons_container->getChildren() as $index => $btn) {
            if ($btn instanceof Button && isset($workflow->steps[$index])) {
                $step = $workflow->steps[$index];
                $btn->status($step->isCurrent);
                $btn->style($step->isCurrent ? 'primary' : ($step->isCompleted ? 'success' : 'secondary'));
            }
        }
    }

    private function buildHeader(): Container
    {
        $header = UI::container('publish_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->padding(Spacing::zero())
            ->add(
                UI::button('btn_back_home')
                    ->label('← Inicio')
                    ->action('go_home')
                    ->style('secondary')
            );

        return $header;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onGoHome(array $params): void
    {
        $this->navigate(Home::class);
    }
}
