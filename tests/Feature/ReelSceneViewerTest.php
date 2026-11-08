<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\UI\Screens\Post\Parts\ReelSceneViewer;
use Idei\Usim\Testing\ScreenTestHarness;

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

it('allows re-embedding ReelSceneViewer with different parameters in OldReelStudio', function () {
    $harness = ScreenTestHarness::for(\App\UI\Screens\Post\Parts\OldReelStudio::class)
        ->render([], shouldReset: true);

    // Initial state: empty state (Escena 0 de 0)
    $harness->assertComponentText('scene_indicator', 'Escena 0 de 0');

    // Action: click 'load' button which calls $this->reel_preview_slot->embed(ReelSceneViewer::class, ['data' => $json])
    $harness->click('load');

    // After re-embedding with new data: Scene 1 of 3
    $harness->assertComponentText('scene_indicator', 'Escena 1 de 3');
});

it('embeds ReelSceneViewer in ReelStudio replacing old preview and supports generate_first_structure', function () {
    $admin = \App\Models\User::factory()->create();
    $this->actingAs($admin);

    $harness = ScreenTestHarness::for(\App\UI\Screens\Post\Parts\ReelStudio::class)
        ->render([], shouldReset: true);

    $harness->assertHasComponent('reel_studio_main_row')
        ->assertHasComponent('left_controls_panel')
        ->assertHasComponent('right_preview_panel')
        ->assertHasComponent('context_received_card')
        ->assertHasComponent('initial_instructions')
        ->assertHasComponent('btn_generate_first_structure')
        ->assertHasComponent('reel_scene_viewer_slot')
        ->assertHasComponent('phone_mockup_container')
        ->assertHasComponent('scene_content_display');

    // 1. Initial state: Empty state in viewer and Contexto recibido card visible
    $htmlContext = (string) $harness->findComponent('context_received_card')->get('html', '');
    expect($htmlContext)->toContain('Contexto recibido')
        ->toContain('Texto Markdown cargado')
        ->toContain('Imágenes adjuntas');

    $htmlViewer = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($htmlViewer)->toContain('Aquí aparecerá la estructura de tu Reel');

    // 2. Click "✨ Generar Primera Estructura"
    $harness->click('btn_generate_first_structure');

    // Viewer now displays the generated structure
    $htmlAfter = (string) $harness->findComponent('scene_content_display')->get('html', '');
    expect($htmlAfter)->toContain('Nueva Beca IDEI');
});




