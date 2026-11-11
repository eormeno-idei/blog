<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PostServiceInterface;
use App\DTOs\ReelStructureGenerationStatus;
use App\Jobs\GenerateReelStructureJob;
use App\Services\User\UsimUserService;
use App\UI\Screens\Post\Parts\ReelSceneViewer;
use App\UI\Screens\Post\Parts\ReelStudio;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Queue;

it('renders empty state when no data is passed to ReelSceneViewer', function () {
    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render([], shouldReset: true);

    $harness->assertHasComponent('phone_mockup_container')
        ->assertHasComponent('scene_content_display')
        ->assertHasComponent('nav_controls_container')
        ->assertHasComponent('btn_prev_scene')
        ->assertHasComponent('scene_indicator')
        ->assertHasComponent('btn_next_scene');

    $content = $harness->findComponent('scene_content_display');
    expect($content)->not->toBeNull();
    $html = (string) $content->get('html', '');

    expect($html)->toContain('Aquí aparecerá la estructura de tu Reel')
        ->toContain('Generar Primera Estructura');

    $harness->assertComponentText('scene_indicator', 'Escena 0 de 0');

    $prevBtn = $harness->findComponent('btn_prev_scene');
    $nextBtn = $harness->findComponent('btn_next_scene');
    expect($prevBtn->get('enabled', true))->toBeFalse();
    expect($nextBtn->get('enabled', true))->toBeFalse();
});

it('renders scenes from _data.json and allows bidirectional navigation', function () {
    $jsonPath = base_path('_data.json');
    expect(file_exists($jsonPath))->toBeTrue();

    $jsonContent = file_get_contents($jsonPath);

    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render(['data' => $jsonContent], shouldReset: true);

    // Initial state: Scene 1 (index 0)
    $harness->assertComponentText('scene_indicator', 'Escena 1 de 3');

    $prevBtn = $harness->findComponent('btn_prev_scene');
    $nextBtn = $harness->findComponent('btn_next_scene');
    expect($prevBtn->get('enabled', true))->toBeFalse();
    expect($nextBtn->get('enabled', false))->toBeTrue();

    $html = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($html)->toContain('Nueva Beca IDEI')
        ->toContain('Proyectos de IA')
        ->toContain('IDEI - FCEFN');

    // Click Next -> Moves to Scene 2 (bullet_points)
    $harness->click('btn_next_scene');

    $harness->assertComponentText('scene_indicator', 'Escena 2 de 3');

    $prevBtn = $harness->findComponent('btn_prev_scene');
    $nextBtn = $harness->findComponent('btn_next_scene');
    expect($prevBtn->get('enabled', false))->toBeTrue();
    expect($nextBtn->get('enabled', false))->toBeTrue();

    $html2 = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($html2)->toContain('Requisitos excluyentes')
        ->toContain('Dominio de Vanilla JS')
        ->toContain('Conocimientos en Laravel 13')
        ->toContain('Estudiante activo UNSJ');

    // Click Next -> Moves to Scene 3 (call_to_action)
    $harness->click('btn_next_scene');

    $harness->assertComponentText('scene_indicator', 'Escena 3 de 3');

    $prevBtn = $harness->findComponent('btn_prev_scene');
    $nextBtn = $harness->findComponent('btn_next_scene');
    expect($prevBtn->get('enabled', false))->toBeTrue();
    expect($nextBtn->get('enabled', true))->toBeFalse();

    $html3 = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($html3)->toContain('Postulate hoy')
        ->toContain('Cierre: 30 de Noviembre')
        ->toContain('Escaneá el código para ver las bases');

    // Click Prev -> Moves back to Scene 2
    $harness->click('btn_prev_scene');

    $harness->assertComponentText('scene_indicator', 'Escena 2 de 3');
});

it('supports loading data directly from a file path', function () {
    $jsonPath = base_path('_data.json');

    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render(['json_path' => $jsonPath], shouldReset: true);

    $harness->assertComponentText('scene_indicator', 'Escena 1 de 3');

    $html = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($html)->toContain('Nueva Beca IDEI');
});

it('matches the screenshot with 5 scenes from data.json showing Escena 1 de 5', function () {
    $jsonPath = base_path('data.json');
    expect(file_exists($jsonPath))->toBeTrue();

    $harness = ScreenTestHarness::for(ReelSceneViewer::class)
        ->render(['json_path' => $jsonPath], shouldReset: true);

    $harness->assertComponentText('scene_indicator', 'Escena 1 de 5');

    $html = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($html)->toContain('Backend-Driven UI en Salud');
});

it('can be embedded into another Screen container with data passed via params', function () {
    $hostScreen = new class extends \Idei\Usim\Screen {
        protected \Idei\Usim\Components\Container $viewer_slot;

        protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void
        {
            $this->viewer_slot = \Idei\Usim\UI::container('viewer_slot');
            $container->add($this->viewer_slot);
        }

        protected function postLoadUI(...$params): void
        {
            $json = file_get_contents(base_path('_data.json'));
            $this->viewer_slot->embed(ReelSceneViewer::class, ['data' => $json]);
        }
    };

    $harness = ScreenTestHarness::for($hostScreen)
        ->render([], shouldReset: true);

    $harness->assertHasComponent('viewer_slot');
    $viewerSlot = $harness->findComponent('viewer_slot');
    expect($viewerSlot)->not->toBeNull();
});

it('embeds ReelSceneViewer in ReelStudio replacing old preview and supports generate_first_structure', function () {
    Queue::fake();

    $userService = app(UsimUserService::class);
    app(RoleAndPermissionSyncService::class)->sync();
    $publisher = $userService->provisionFromConfig('publisher');
    $this->actingAs($publisher);

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
        ->assertHasComponent('scene_content_display');

    expect($harness->findComponent('left_controls_panel'))->toBeNull()
        ->and($harness->findComponent('right_preview_panel'))->toBeNull();

    // 1. Initial state: Empty state in viewer and Contexto recibido labels visible
    $harness->assertComponentText('context_received_title', '✅ Contexto recibido')
        ->assertComponentText('context_received_content', '✅ Texto Markdown cargado')
        ->assertComponentText('context_received_images', '✅ Imágenes adjuntas');

    $htmlViewer = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($htmlViewer)->toContain('Aquí aparecerá la estructura de tu Reel');

    // 2. Click "✨ Generar Primera Estructura" -> dispatches job and starts timer
    $harness->click('btn_generate_first_structure', [
        'post_id' => $postId,
        'initial_instructions' => 'Enfocar en estudiantes de Informática',
    ]);

    Queue::assertPushed(GenerateReelStructureJob::class);

    // 3. Simulate completed job status and trigger polling + completion event
    $sampleJson = (string) file_get_contents(base_path('_data.json'));
    $decoded = json_decode($sampleJson, true);
    GenerateReelStructureJob::putStatus(ReelStructureGenerationStatus::completed(
        postId: $postId,
        sceneCount: count($decoded['scenes']),
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
    $htmlAfter = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($htmlAfter)->toContain('Nueva Beca IDEI');
});




