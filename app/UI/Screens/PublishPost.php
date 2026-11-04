<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\WorkflowStateDTO;
use App\Services\Post\PostWorkflowService;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\JustifyContent;
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

    protected function buildBaseUI(Container $container, ...$params): void
    {

        $postId = $this->resolvePostId($params);
        $stepIndex = $this->resolveCurrentStepIndex();
        if ($postId === null) {
            $this->navigate(Home::class);
            return;
        }

        $workflow = $this->workflowService->getWorkflowState($postId, $stepIndex);

        $this->demo_container = UI::container('demo_container')
            ->card()
            ->shadow(false)
            ->width(Size::full())
            ->height(Size::px(500));

        $wrapper = UI::container('wrapper')
            ->width(Size::full())
            ->gap(Spacing::px(5));

        $wrapper->add($this->buildStepsBar($workflow));
        $wrapper->add($this->demo_container);
        $wrapper->add($this->buildNavigation($workflow));

        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        $postId = $this->resolvePostId();
        $stepIndex = $this->resolveCurrentStepIndex();
        if ($postId === null) {
            return;
        }

        $workflow = $this->workflowService->getWorkflowState($postId, $stepIndex);
        $this->demo_container->embed($workflow->currentStep()->screenClass);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onGoToNextStep(array $params): void
    {
        $postId = $this->resolvePostId();
        if ($postId === null) {
            return;
        }

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

    /**
     * @param array<string, mixed> $params
     */
    public function onGoToPreviousStep(array $params): void
    {
        if ($this->state_currentStepIndex <= 0) {
            return;
        }

        $postId = $this->resolvePostId();
        if ($postId === null) {
            return;
        }

        $this->state_currentStepIndex--;
        $newWorkflow = $this->workflowService->getWorkflowState($postId, $this->state_currentStepIndex);

        $this->demo_container->embed($newWorkflow->currentStep()->screenClass);
        $this->refreshNavigationState($newWorkflow);
    }

    /**
     * @param array<string, mixed> $params
     * @return ?int
     */
    private function resolvePostId(array $params = []): ?int
    {
        if (isset($params['post_id']) && is_numeric($params['post_id']) && (int) $params['post_id'] > 0) {
            $this->state_post_id = (int) $params['post_id'];
            return $this->state_post_id;
        }
        
        $rawPostId = $this->getQueryParams()['post_id'] ?? null;
        if (is_numeric($rawPostId) && (int) $rawPostId > 0) {
            $this->state_post_id = (int) $rawPostId;
        }
        return $this->state_post_id > 0 ? $this->state_post_id : null;
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
            ->gap(Spacing::px(10));

        foreach ($workflow->steps as $index => $step) {
            $button = UI::button("step_btn_{$index}")
                ->label($step->label)
                ->status($step->isCurrent)
                ->style($step->isCurrent ? 'primary' : ($step->isCompleted ? 'success' : 'secondary'));

            if ($step->canAccess) {
                $button->action('on_select_step', ['index' => $index]);
            }

            $this->buttons_container->add($button);
        }

        return $this->buttons_container;
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
}
