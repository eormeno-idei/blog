<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\WorkflowStateDTO;
use App\Services\Post\PostWorkflowService;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class PublishPost extends Screen
{
    protected Container $buttons_container;
    protected Container $demo_container;
    protected ?int $state_currentStepIndex = null;

    public function __construct(
        protected PostServiceInterface $postService,
        protected PostWorkflowService $workflowService,
    ) {
        parent::__construct();
    }

    protected function buildBaseUI(Container $container, int $post_id): void
    {
        $container->padding(Spacing::zero());
        $workflow = $this->workflowService->getWorkflowState($post_id, $this->state_currentStepIndex);

        $this->demo_container = UI::container('demo_container')
            ->card()
            ->centerHorizontal()
            ->width(Size::px(1280))
            ->height(Size::px(600));

        $wrapper = UI::container('wrapper')
            ->width(Size::full())
            ->padding(Spacing::zero())
            ->gap(Spacing::px(4));

        $wrapper->add($this->buildStepsBar($post_id, $workflow));
        $wrapper->add($this->demo_container);

        $container->add($wrapper);
    }

    protected function postLoadUI(int $post_id): void
    {
        $workflow = $this->workflowService->getWorkflowState($post_id, $this->state_currentStepIndex);
        $this->demo_container->embed($workflow->currentStep()->screenClass);
    }

    private function buildStepsBar(int $post_id, WorkflowStateDTO $workflow): Container
    {
        $this->buttons_container = UI::container('buttons_container')
            ->layout(LayoutType::HORIZONTAL)
            ->width(Size::px(1280))
            ->centerHorizontal()
            ->padding(Spacing::zero())
            ->gap(Spacing::px(10));

        $this->buttons_container->add(
            UI::button('btn_back_home')
                ->label('← Inicio')
                ->action('go_home')
                ->style('secondary')
        );

        foreach ($workflow->steps as $index => $step) {
            $index_inc = $index + 1;
            $style = $step->isCurrent ? 'primary' : 'secondary';

            $button = UI::button("step_btn_{$index}")
                ->label("{$index_inc}. {$step->label}")
                ->status($step->isCurrent)
                ->enabled($step->canAccess)
                ->icon($step->isCompleted ? '✅' : '🔲')
                ->style($style)
                ->action('on_select_step', [
                    'post_id' => $post_id,
                    'index' => $index
                ]);

            $this->buttons_container->add($button);
        }

        return $this->buttons_container;
    }

    public function onSelectStep(int $post_id, int $index): void
    {
        $workflow = $this->workflowService->getWorkflowState($post_id, $index);
        if (!$workflow->steps[$index]->canAccess) {
            $this->toast('No puedes acceder a este paso. Completa los pasos anteriores primero.', 'warning');
            return;
        }

        $this->state_currentStepIndex = $index;
        $this->demo_container->embed($workflow->currentStep()->screenClass);
        $this->refreshNavigationState($workflow);
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

    private function refreshNavigationState(WorkflowStateDTO $workflow): void
    {
        foreach ($this->buttons_container->getChildren() as $index => $btn) {
            if ($btn instanceof Button && isset($workflow->steps[$index])) {
                $step = $workflow->steps[$index];
                $btn->status($step->isCurrent);
                $btn->icon($step->isCompleted ? '✅' : '🔲');
                $btn->style($step->isCurrent ? 'primary' : 'secondary');
            }
        }
    }

    public function onGoHome(): void
    {
        $this->navigate(Home::class);
    }
}
