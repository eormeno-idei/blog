<?php

declare(strict_types=1);

use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostServiceInterface;
use App\Jobs\GenerateReelStructureJob;
use App\Services\User\UsimUserService;
use App\UI\Screens\Post\Parts\DateTimeData;
use App\UI\Screens\Post\Parts\InitialData;
use App\UI\Screens\Post\Parts\ReelStudio;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('exercises the complete wizard lifecycle in PublishPost', function () {
    /** @var TestCase $this */
    Queue::fake();

    $userService = app(UsimUserService::class);
    $publisher = $userService->provisionFromConfig('publisher');
    $reviewer = $userService->provisionFromConfig('reviewer');
    $postService = app(PostServiceInterface::class);

    $this->actingAs($publisher);

    $postId = $postService->createPost([
        'user_id' => $publisher->id,
        'status' => 'draft',
    ]);

    /** @var PublishPost $screen */
    $screen = Screen::make(PublishPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->withQuery(['post_id' => $postId])
        ->render(['post_id' => $postId], shouldReset: true);

    $buttonsContainer = $screen->getContainer()?->findByName('buttons_container');
    $demoContainer = $screen->getContainer()?->findByName('demo_container');
    expect($buttonsContainer)->not->toBeNull()
        ->and($demoContainer)->not->toBeNull();

    // -------------------------------------------------------------
    // Paso 1: Datos Iniciales (InitialData)
    // -------------------------------------------------------------
    $harness->assertHasComponent('post_title')
        ->assertHasComponent('post_content')
        ->assertHasComponent('confirm_button');

    /** @var InitialData $initialDataScreen */
    $initialDataScreen = Screen::make(InitialData::class);
    $initialHarness = ScreenTestHarness::for($initialDataScreen);
    $initialHarness->render(['post_id' => $postId], shouldReset: true);

    $validContent = 'Inscripciones abiertas para estudiantes de grado y posgrado con dedicación exclusiva en el instituto.';
    $initialHarness->call('on_confirm_initial_data', [
        'post_id' => $postId,
        'post_title' => 'Convocatoria a Becas 2026',
        'post_content' => $validContent,
    ]);

    $post = $postService->findById($postId);
    expect($post)->not->toBeNull()
        ->and($post?->title)->toBe('Convocatoria a Becas 2026')
        ->and($post?->content)->toBe($validContent)
        ->and($post?->statusValue())->toBe('draft');

    // Al recargar PublishPost con Paso 1 completado, avanza automáticamente al Paso 2 (DateTimeData)
    $harness->render(['post_id' => $postId], shouldReset: true);
    $harness->assertHasComponent('post_from')
        ->assertHasComponent('post_to')
        ->assertHasComponent('confirm_dates');

    // -------------------------------------------------------------
    // Paso 2: Fecha y Hora (DateTimeData)
    // -------------------------------------------------------------
    /** @var DateTimeData $dateTimeScreen */
    $dateTimeScreen = Screen::make(DateTimeData::class);
    $dateTimeHarness = ScreenTestHarness::for($dateTimeScreen);
    $dateTimeHarness->render(['post_id' => $postId], shouldReset: true);

    $fromDate = now()->addDays(2)->format('Y-m-d');
    $toDate = now()->addDays(20)->format('Y-m-d');

    $dateTimeHarness->call('on_confirm_dates', [
        'post_id' => $postId,
        'post_from' => $fromDate,
        'post_to' => $toDate,
    ]);

    $postAfterDates = $postService->findById($postId);
    expect($postAfterDates?->from)->not->toBeNull()
        ->and($postAfterDates?->to)->not->toBeNull();

    // -------------------------------------------------------------
    // Paso 3: Reel Studio (Generación de estructura con IA y preview)
    // -------------------------------------------------------------
    $harness->call('on_select_step', [
        'post_id' => $postId,
        'index' => 2,
    ]);

    $harness->assertHasComponent('cnt_reel_studio_main_row')
        ->assertHasComponent('cnt_first_draft_panel')
        ->assertHasComponent('cnt_edit_panel')
        ->assertHasComponent('cnt_reel_scene_viewer_slot');

    /** @var ReelStudio $reelStudioScreen */
    $reelStudioScreen = Screen::make(ReelStudio::class);
    $reelStudioHarness = ScreenTestHarness::for($reelStudioScreen);
    $reelStudioHarness->render(['post_id' => $postId], shouldReset: true);

    $reelStudioHarness->click('btn_generate_first_structure', [
        'postId' => $postId,
        'txt_initial_instructions' => 'Resaltar beneficios de la beca',
    ]);

    Queue::assertPushed(GenerateReelStructureJob::class);

    $sampleScript = [
        'metadata' => ['autor' => 'UNSJ', 'duracion_total_segundos' => 15],
        'scenes' => [
            [
                'scene_index' => 1,
                'layout_template' => 'title_hero',
                'duration_seconds' => 7,
                'text_content' => [
                    'headline' => 'Becas UNSJ 2026',
                    'subheadline' => 'Convocatoria Abierta',
                    'body' => ['Para grado y posgrado'],
                ],
            ],
        ],
    ];

    $historyService = app(PostHistoryServiceInterface::class);
    $reqEvent = $historyService->getLatestReelEvent($postId);
    if ($reqEvent !== null) {
        $this->travel(1)->seconds();
        $genEvent = $historyService->createGeneratingReelScriptEvent($reqEvent);
        $this->travel(1)->seconds();
        $historyService->createCompletedReelScriptEvent($genEvent, $sampleScript);
    }

    $reelStudioHarness->call('check_reel_status', ['post_id' => $postId]);
    $reelStudioHarness->call('ai_first_structure_completed', [
        'postId' => $postId,
        'script' => $sampleScript,
    ]);
    $reelStudioHarness->assertComponentText('lbl_scene_headline', 'Becas UNSJ 2026');

    // -------------------------------------------------------------
    // Revisión y moderación por el revisor
    // -------------------------------------------------------------
    $this->actingAs($reviewer);
    $postService->requestChanges($postId, $reviewer, 'Por favor corregir las fechas de entrega de formularios.');
    expect($postService->findById($postId)?->statusValue())->toBe('changes_requested');

    $postService->approvePost($postId, $reviewer);
    expect($postService->findById($postId)?->statusValue())->toBe('published');

    // Verificar botones de pasos en PublishPost
    $this->actingAs($publisher);
    $harness->render(['post_id' => $postId], shouldReset: true);
    /** @var Button|null $stepBtn0 */
    $stepBtn0 = $screen->getContainer()?->findByName('step_btn_0');
    /** @var Button|null $stepBtn1 */
    $stepBtn1 = $screen->getContainer()?->findByName('step_btn_1');
    expect($stepBtn0?->get('enabled'))->toBeTrue()
        ->and($stepBtn1?->get('enabled'))->toBeTrue();
});
