<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Post\Parts\ReelStudio;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Container;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('configures ReelStudio main, first-draft, and edit panels using declarative USIM 2.6 layouts without wrapper containers', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $harness = ScreenTestHarness::for(ReelStudio::class)
        ->render([], shouldReset: true);

    $root = $harness->getContainer();
    expect($root)->not->toBeNull();

    $json = $root->toJson();

    // Removed intermediate wrapper containers must not exist
    foreach ([
        'left_controls_panel',
        'right_preview_panel',
        'mode_switch_container',
        'prompt_box_container',
        'quick_actions_grid',
        'btn_edit_scene',
        'btn_edit_all_reel',
        'edit_prompt_input',
        'btn_submit_prompt',
        'lbl_quick_actions',
        'btn_summarize_text',
        'btn_expand_details',
        'btn_reduce_time',
        'btn_increase_time',
        'btn_continue_to_design',
    ] as $removedComponent) {
        expect($harness->findComponent($removedComponent))->toBeNull();
    }

    // 1. Main 2-column grid container (reel-studio.layout.yaml)
    /** @var Container $mainRow */
    $mainRow = $harness->getComponent('reel_studio_main_row');
    $mainCfg = $json[$mainRow->getId()];

    expect($mainCfg['layout'])->toBe('grid')
        ->and($mainCfg['appearance'])->toBe('card')
        ->and($mainCfg['width'])->toBe('100%')
        ->and($mainCfg['max_width'])->toBe('800px')
        ->and($mainCfg['padding'])->toBe('8px')
        ->and($mainCfg['gap'])->toBe('16px')
        ->and($mainCfg['grid_template_columns'])->toBe('1fr 1fr');

    // Direct children of reel_studio_main_row
    /** @var Container $firstDraftPanel */
    $firstDraftPanel = $harness->getComponent('first_draft_panel');
    /** @var Container $editPanel */
    $editPanel = $harness->getComponent('edit_panel');
    /** @var Container $viewerSlot */
    $viewerSlot = $harness->getComponent('reel_scene_viewer_slot');

    expect($firstDraftPanel->getParent())->toBe($mainRow->getId())
        ->and($editPanel->getParent())->toBe($mainRow->getId())
        ->and($viewerSlot->getParent())->toBe($mainRow->getId());

    $firstDraftCfg = $json[$firstDraftPanel->getId()];
    $editCfg = $json[$editPanel->getId()];
    $viewerSlotCfg = $json[$viewerSlot->getId()];

    expect($firstDraftCfg['grid_column'])->toBe('1')
        ->and($firstDraftCfg['grid_row'])->toBe('1')
        ->and($firstDraftPanel->isVisible())->toBeTrue()
        ->and($editCfg['grid_column'])->toBe('1')
        ->and($editCfg['grid_row'])->toBe('1')
        ->and($editPanel->isVisible())->toBeFalse()
        ->and($viewerSlotCfg['grid_column'])->toBe('2')
        ->and($viewerSlotCfg['grid_row'])->toBe('1')
        ->and($viewerSlotCfg['align_self'])->toBe('stretch')
        ->and($viewerSlotCfg['justify_self'])->toBe('stretch')
        ->and($viewerSlotCfg['width'])->toBe('100%');

    // 2. First-draft panel declarative layout (reel-studio-first-draft.layout.yaml)
    expect($firstDraftCfg['layout'])->toBe('grid')
        ->and($firstDraftCfg['appearance'])->toBe('plain')
        ->and($firstDraftCfg['width'])->toBe('100%')
        ->and($firstDraftCfg['height'])->toBe('100%')
        ->and($firstDraftCfg['padding'])->toBe('0')
        ->and($firstDraftCfg['gap'])->toBe('8px')
        ->and($firstDraftCfg['grid_template_columns'])->toBe('1fr')
        ->and($firstDraftCfg['grid_template_rows'])->toBe('auto 1fr auto');

    $contextCard = $harness->getComponent('context_received_card');
    $instructions = $harness->getComponent('initial_instructions');
    $btnGenerate = $harness->getComponent('btn_generate_first_structure');
    $timer = $harness->getComponent('tmr_reel_poll');

    expect($contextCard->getParent())->toBe($firstDraftPanel->getId())
        ->and($instructions->getParent())->toBe($firstDraftPanel->getId())
        ->and($btnGenerate->getParent())->toBe($firstDraftPanel->getId())
        ->and($timer->getParent())->toBe($firstDraftPanel->getId());

    expect($json[$contextCard->getId()]['grid_row'])->toBe('1')
        ->and($json[$instructions->getId()]['grid_row'])->toBe('2')
        ->and($json[$instructions->getId()]['width'])->toBe('100%')
        ->and($json[$btnGenerate->getId()]['grid_row'])->toBe('3')
        ->and($json[$btnGenerate->getId()]['width'])->toBe('100%');

    // 3. Edit panel grid layout (reel-studio-edit.layout.yaml)
    expect($editCfg['layout'])->toBe('grid')
        ->and($editCfg['appearance'])->toBe('plain')
        ->and($editCfg['width'])->toBe('100%')
        ->and($editCfg['height'])->toBe('100%')
        ->and($editCfg['padding'])->toBe('0')
        ->and($editCfg['gap'])->toBe('16px')
        ->and($editCfg['grid_template_columns'])->toBe('1fr')
        ->and($editCfg['grid_template_rows'])->toBe('auto auto 1fr auto');

    $expectedEditPlacement = [
        'lbl_post_title' => ['1', '1'],
        'cnt_status_row' => ['1', '2'],
        'lst_history' => ['1', '3'],
        'cnt_waiting_creator' => ['1', '4'],
    ];

    foreach ($expectedEditPlacement as $name => [$expectedCol, $expectedRow]) {
        $component = $harness->getComponent($name);
        $cfg = $json[$component->getId()];

        expect($component->getParent())->toBe($editPanel->getId())
            ->and($cfg['grid_column'])->toBe($expectedCol)
            ->and($cfg['grid_row'])->toBe($expectedRow);
    }

    $lstHistory = $harness->getComponent('lst_history');
    $lstCfg = $json[$lstHistory->getId()];
    expect($lstCfg['selection_mode'])->toBe('single')
        ->and($lstCfg['variant'])->toBe('card')
        ->and($lstCfg['title'])->toBe('Historial de versiones')
        ->and(count($lstCfg['items']))->toBe(3);

    $cntWaiting = $harness->getComponent('cnt_waiting_creator');
    $cntWaitingCfg = $json[$cntWaiting->getId()];
    expect($cntWaitingCfg['appearance'])->toBe('card');
});

it('preserves reel_scene_viewer_slot grid placement when hot-swapping ReelSceneViewer on completion', function () {
    $publisher = app(UsimUserService::class)->provisionFromConfig('publisher');
    $this->actingAs($publisher);

    $postService = app(PostServiceInterface::class);
    $sampleJson = (string) file_get_contents(base_path('_data.json'));

    $postId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Post con Reel',
        'content' => 'Contenido de prueba para ReelStudio',
    ]);

    $harness = ScreenTestHarness::for(ReelStudio::class)
        ->render(['post_id' => $postId], shouldReset: true);

    $postService->updatePost($postId, ['ai_script' => $sampleJson], $publisher);

    $harness->call('ai_first_structure_completed', [
        'postId' => $postId,
        'json' => $sampleJson,
    ]);

    $json = $harness->getContainer()?->toJson() ?? [];
    $viewerSlot = $harness->getComponent('reel_scene_viewer_slot');
    $slotCfg = $json[$viewerSlot->getId()];

    expect($slotCfg['grid_column'])->toBe('2')
        ->and($slotCfg['grid_row'])->toBe('1')
        ->and($slotCfg['align_self'])->toBe('stretch')
        ->and($slotCfg['justify_self'])->toBe('stretch');

    $headlineAfter = (string) $harness->getComponent('lbl_scene_headline')->get('text', '');
    expect($headlineAfter)->toBe('Nueva Beca IDEI');
});

it('embeds cleanly inside PublishPost step 2 with declarative layouts preserved', function () {
    $publisher = app(UsimUserService::class)->provisionFromConfig('publisher');
    $this->actingAs($publisher);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Convocatoria de Becas IDEI 2026',
        'content' => 'Convocatoria abierta para estudiantes avanzados de informática interesados en proyectos de inteligencia artificial.',
        'from' => now()->addDay()->toDateString(),
        'to' => now()->addDays(15)->toDateString(),
    ]);

    $harness = ScreenTestHarness::for(PublishPost::class)
        ->withQuery(['post_id' => $postId])
        ->render(['post_id' => $postId], shouldReset: true);

    $harness->assertHasComponent('demo_container')
        ->assertHasComponent('reel_studio_main_row')
        ->assertHasComponent('first_draft_panel')
        ->assertHasComponent('edit_panel')
        ->assertHasComponent('reel_scene_viewer_slot')
        ->assertHasComponent('phone_mockup_container');

    $json = $harness->getContainer()?->toJson() ?? [];
    $mainRow = $harness->getComponent('reel_studio_main_row');
    $editPanel = $harness->getComponent('edit_panel');

    expect($json[$mainRow->getId()]['layout'])->toBe('grid')
        ->and($json[$mainRow->getId()]['grid_template_columns'])->toBe('1fr 1fr')
        ->and($json[$editPanel->getId()]['layout'])->toBe('grid')
        ->and($json[$editPanel->getId()]['grid_template_columns'])->toBe('1fr');
});
