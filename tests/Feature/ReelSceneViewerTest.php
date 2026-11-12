<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PostServiceInterface;
use App\DTOs\ReelStructureGenerationStatus;
use App\Jobs\GenerateReelStructureJob;
use App\Services\User\UsimUserService;
use App\UI\Screens\Post\Parts\ReelSceneViewer;
use App\UI\Screens\Post\Parts\ReelStudio;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

it('renders empty state when no data is passed to ReelSceneViewer', function () {
    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render([], shouldReset: true);

    $harness->assertHasComponent('phone_mockup_container')
        ->assertHasComponent('lbl_empty_title')
        ->assertHasComponent('lbl_empty_subtitle')
        ->assertHasComponent('btn_prev_scene')
        ->assertHasComponent('scene_indicator')
        ->assertHasComponent('btn_next_scene');

    expect($harness->findComponent('scene_header_container'))->toBeNull()
        ->and($harness->findComponent('scene_body_container'))->toBeNull()
        ->and($harness->findComponent('nav_controls_container'))->toBeNull();

    $mockup = $harness->getComponent('phone_mockup_container');
    $json = $harness->getContainer()?->toJson() ?? [];
    $mockupCfg = $json[$mockup->getId()];
    expect($mockupCfg['appearance'])->toBe('card')
        ->and($mockupCfg['background_color'] ?? null)->toBeNull();

    $harness->assertComponentText('lbl_empty_title', 'Aquí aparecerá la estructura de tu Reel')
        ->assertComponentText('lbl_empty_subtitle', "Haz clic en 'Generar Primera Estructura' para que la IA procese tu información.")
        ->assertComponentText('scene_indicator', 'Escena 0 de 0');

    expect($harness->getComponent('lbl_empty_title')->isVisible())->toBeTrue()
        ->and($harness->getComponent('lbl_scene_headline')->isVisible())->toBeFalse();

    /** @var Button $prevBtn */
    $prevBtn = $harness->getComponent('btn_prev_scene');
    /** @var Button $nextBtn */
    $nextBtn = $harness->getComponent('btn_next_scene');
    expect($prevBtn->get('enabled', true))->toBeFalse();
    expect($nextBtn->get('enabled', true))->toBeFalse();
});

it('renders scenes from _data.json and allows bidirectional navigation using native USIM components', function () {
    $jsonPath = base_path('_data.json');
    expect(file_exists($jsonPath))->toBeTrue();

    $jsonContent = file_get_contents($jsonPath);

    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render(['data' => $jsonContent], shouldReset: true);

    // Initial state: Scene 1 (index 0)
    $harness->assertComponentText('scene_indicator', 'Escena 1 de 3')
        ->assertComponentText('lbl_scene_meta', 'IDEI - FCEFN')
        ->assertComponentText('lbl_scene_duration', '⏱ 4 s')
        ->assertComponentText('lbl_scene_headline', 'Nueva Beca IDEI')
        ->assertComponentText('lbl_scene_subheadline', 'Proyectos de IA');

    expect($harness->getComponent('lbl_empty_title')->isVisible())->toBeFalse()
        ->and($harness->getComponent('scene_body_list')->isVisible())->toBeFalse();

    /** @var Button $prevBtn */
    $prevBtn = $harness->getComponent('btn_prev_scene');
    /** @var Button $nextBtn */
    $nextBtn = $harness->getComponent('btn_next_scene');
    expect($prevBtn->get('enabled', true))->toBeFalse();
    expect($nextBtn->get('enabled', false))->toBeTrue();

    // Click Next -> Moves to Scene 2 (bullet_points)
    $harness->click('btn_next_scene');

    $harness->assertComponentText('scene_indicator', 'Escena 2 de 3')
        ->assertComponentText('lbl_scene_duration', '⏱ 6 s')
        ->assertComponentText('lbl_scene_headline', 'Requisitos excluyentes')
        ->assertComponentText('scene_body_item_0', '• Dominio de Vanilla JS')
        ->assertComponentText('scene_body_item_1', '• Conocimientos en Laravel 13')
        ->assertComponentText('scene_body_item_2', '• Estudiante activo UNSJ');

    expect($harness->getComponent('lbl_scene_subheadline')->isVisible())->toBeFalse()
        ->and($harness->getComponent('scene_body_list')->isVisible())->toBeTrue();

    /** @var Button $prevBtn */
    $prevBtn = $harness->getComponent('btn_prev_scene');
    /** @var Button $nextBtn */
    $nextBtn = $harness->getComponent('btn_next_scene');
    expect($prevBtn->get('enabled', false))->toBeTrue();
    expect($nextBtn->get('enabled', false))->toBeTrue();

    // Click Next -> Moves to Scene 3 (call_to_action)
    $harness->click('btn_next_scene');

    $harness->assertComponentText('scene_indicator', 'Escena 3 de 3')
        ->assertComponentText('lbl_scene_duration', '⏱ 4 s')
        ->assertComponentText('lbl_scene_headline', 'Postulate hoy')
        ->assertComponentText('lbl_scene_subheadline', 'Cierre: 30 de Noviembre')
        ->assertComponentText('scene_body_item_0', '• Escaneá el código para ver las bases');

    /** @var Button $prevBtn */
    $prevBtn = $harness->getComponent('btn_prev_scene');
    /** @var Button $nextBtn */
    $nextBtn = $harness->getComponent('btn_next_scene');
    expect($prevBtn->get('enabled', false))->toBeTrue();
    expect($nextBtn->get('enabled', true))->toBeFalse();

    // Click Prev -> Moves back to Scene 2
    $harness->click('btn_prev_scene');

    $harness->assertComponentText('scene_indicator', 'Escena 2 de 3')
        ->assertComponentText('lbl_scene_headline', 'Requisitos excluyentes');
});

it('supports loading data directly from a file path', function () {
    $jsonPath = base_path('_data.json');

    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render(['json_path' => $jsonPath], shouldReset: true);

    $harness->assertComponentText('scene_indicator', 'Escena 1 de 3')
        ->assertComponentText('lbl_scene_headline', 'Nueva Beca IDEI');
});

it('matches 5 scenes from data.json showing Escena 1 de 5', function () {
    $jsonPath = base_path('data.json');
    expect(file_exists($jsonPath))->toBeTrue();

    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render(['json_path' => $jsonPath], shouldReset: true);

    $harness->assertComponentText('scene_indicator', 'Escena 1 de 5')
        ->assertComponentText('lbl_scene_headline', 'Backend-Driven UI en Salud')
        ->assertComponentText('lbl_scene_subheadline', 'Proyecto PDTS - Universidad Nacional de San Juan')
        ->assertComponentText('scene_body_item_0', '• Convocatoria 2025');
});

it('can be embedded into another Screen container with data passed via params', function () {
    $hostScreen = new class extends Screen {
        protected Container $viewer_slot;

        protected function startingUI(): Container
        {
            return $this->viewer_slot;
        }

        protected function postLoadUI(): void
        {
            $json = file_get_contents(base_path('_data.json'));
            $this->viewer_slot->embed(ReelSceneViewer::class, ['data' => $json]);
        }
    };

    $harness = ScreenTestHarness::for($hostScreen)
        ->render([], shouldReset: true);

    $harness->assertHasComponent('viewer_slot')
        ->assertHasComponent('phone_mockup_container')
        ->assertComponentText('lbl_scene_headline', 'Nueva Beca IDEI');
});

it('embeds ReelSceneViewer in ReelStudio replacing old preview and supports generate_first_structure', function () {
    Queue::fake();

    $userService = app(UsimUserService::class);
    app(RoleAndPermissionSyncService::class)->sync();
    $publisher = $userService->provisionFromConfig('publisher');
    Auth::login($publisher);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $publisher->id,
        'title' => 'Nueva Beca IDEI',
        'content' => 'Convocatoria abierta para proyectos de IA en UNSJ.',
    ]);

    $harness = ScreenTestHarness::for(ReelStudio::class)
        ->render(['post_id' => $postId], shouldReset: true);

    $harness->assertHasComponent('reel_studio_main_row')
        ->assertHasComponent('first_draft_panel')
        ->assertHasComponent('edit_panel')
        ->assertHasComponent('context_received_card')
        ->assertHasComponent('context_received_title')
        ->assertHasComponent('context_received_content')
        ->assertHasComponent('context_received_images')
        ->assertHasComponent('initial_instructions')
        ->assertHasComponent('btn_generate_first_structure')
        ->assertHasComponent('reel_scene_viewer_slot')
        ->assertHasComponent('phone_mockup_container')
        ->assertHasComponent('lbl_empty_title');

    expect($harness->findComponent('left_controls_panel'))->toBeNull()
        ->and($harness->findComponent('right_preview_panel'))->toBeNull();

    // 1. Initial state: Empty state in viewer and Contexto recibido labels visible
    $harness->assertComponentText('context_received_title', '✅ Contexto recibido')
        ->assertComponentText('context_received_content', '✅ Texto Markdown cargado')
        ->assertComponentText('context_received_images', '✅ Imágenes adjuntas')
        ->assertComponentText('lbl_empty_title', 'Aquí aparecerá la estructura de tu Reel');

    // 2. Click "✨ Generar Primera Estructura" -> dispatches job and starts timer
    $harness->click('btn_generate_first_structure', [
        'post_id' => $postId,
        'initial_instructions' => 'Enfocar en estudiantes de Informática',
    ]);

    Queue::assertPushed(GenerateReelStructureJob::class);

    // 3. Simulate completed job status and trigger polling + completion event
    $sampleJson = (string) file_get_contents(base_path('_data.json'));
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($sampleJson, true);
    /** @var array<int, array<string, mixed>> $scenes */
    $scenes = is_array($decoded['scenes'] ?? null) ? $decoded['scenes'] : [];

    GenerateReelStructureJob::putStatus(ReelStructureGenerationStatus::completed(
        postId: $postId,
        sceneCount: count($scenes),
        durationSeconds: 30,
        jsonScript: $sampleJson,
        parsedScript: $decoded,
    ));

    $harness->call('check_reel_status', ['post_id' => $postId]);
    $harness->call('ai_first_structure_completed', [
        'postId' => $postId,
        'json' => $sampleJson,
    ]);

    // Viewer now displays the generated structure
    $harness->assertComponentText('lbl_scene_headline', 'Nueva Beca IDEI');
});
