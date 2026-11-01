<?php

declare(strict_types=1);

use App\Ai\Agents\PostAgent;
use App\Contracts\PostServiceInterface;
use App\DTOs\ReelGenerationStatus;
use App\Jobs\GenerateReelPreviewJob;
use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Screens\Home;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Select;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('exercises the complete 7-step wizard lifecycle in PublishPost', function () {
    Queue::fake();

    $userService = app(UsimUserService::class);
    $publisher = $userService->provisionFromConfig('publisher');
    $reviewer = $userService->provisionFromConfig('reviewer');
    $admin = $userService->provisionFromConfig('admin');
    $postService = app(PostServiceInterface::class);

    $this->actingAs($publisher);

    /** @var PublishPost $screen */
    $screen = Screen::make(PublishPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render([], shouldReset: true);

    $tabsContainer = $screen->getContainer()?->findByName('workspace_tabs');
    expect($tabsContainer)->not->toBeNull();

    // -------------------------------------------------------------
    // Paso 1: Datos (Título, Contenido/Notas, Fechas)
    // -------------------------------------------------------------
    $harness->call('step1_next', [
        'post_title' => 'Convocatoria a Becas 2026',
        'post_content' => 'Inscripciones abiertas para estudiantes de grado y posgrado con dedicación exclusiva.',
        'post_from' => '2026-11-01',
        'post_to' => '2026-11-30',
    ]);

    $post = $postService->getLatestPosts($publisher)->firstWhere('title', 'Convocatoria a Becas 2026');
    expect($post)->not->toBeNull()
        ->and($post->content)->toBe('Inscripciones abiertas para estudiantes de grado y posgrado con dedicación exclusiva.')
        ->and($post->status)->toBe('draft');

    $tabsContainer = $screen->getContainer()?->findByName('workspace_tabs');
    $tabsRaw = $tabsContainer ? ($tabsContainer->toJson()[$tabsContainer->getId()] ?? []) : [];
    expect($tabsRaw['tabs_active'] ?? null)->toBe('step_image_tab');

    // -------------------------------------------------------------
    // Paso 2: Imagen (Uploader opcional y navegación atrás/siguiente)
    // -------------------------------------------------------------
    // Prueba de retroceso al Paso 1
    $harness->call('step2_back');
    $tabsBack = $screen->getContainer()?->findByName('workspace_tabs');
    $tabsRawBack = $tabsBack ? ($tabsBack->toJson()[$tabsBack->getId()] ?? []) : [];
    expect($tabsRawBack['tabs_active'] ?? null)->toBe('form_tab');

    // Avanzar nuevamente al Paso 2 y luego al Paso 3
    $harness->call('step2_next', [
        'post_id' => (string) $post->id,
    ]);
    $tabsAfterStep2 = $screen->getContainer()?->findByName('workspace_tabs');
    $tabsRawAfterStep2 = $tabsAfterStep2 ? ($tabsAfterStep2->toJson()[$tabsAfterStep2->getId()] ?? []) : [];
    expect($tabsRawAfterStep2['tabs_active'] ?? null)->toBe('reel_studio_tab');

    // -------------------------------------------------------------
    // Paso 3: Estructurar Reel con IA (LLM y simulación visual previa al video)
    // -------------------------------------------------------------
    $sampleScript = (string) json_encode([
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
            [
                'scene_index' => 2,
                'layout_template' => 'call_to_action',
                'duration_seconds' => 8,
                'text_content' => [
                    'headline' => 'Inscribite ahora',
                    'subheadline' => 'becas.unsj.edu.ar',
                    'body' => ['Hasta el 30 de noviembre'],
                ],
            ],
        ],
    ]);

    $harness->call('ask_ai', [
        'post_content' => 'Inscripciones abiertas para estudiantes de grado y posgrado.',
    ]);

    GenerateReelPreviewJob::putStatus($post->id, new ReelGenerationStatus(
        status: 'completed',
        version: 1,
        sceneCount: 2,
        durationSeconds: 15,
        jsonScript: $sampleScript,
        parsedScript: json_decode($sampleScript, true),
        videoFilename: '',
    ));
    $harness->call('check_reel_status');

    /** @var Carousel|null $simulatorPlayer */
    $simulatorPlayer = $screen->getContainer()?->findByName('reel_preview_player');
    expect($simulatorPlayer)->not->toBeNull()
        ->and($simulatorPlayer?->get('width'))->toBe('315px')
        ->and($simulatorPlayer?->get('height'))->toBe('560px');

    /** @var Button|null $step3NextBtn */
    $step3NextBtn = $screen->getContainer()?->findByName('btn_step3_next');
    expect($step3NextBtn?->get('enabled'))->toBeTrue();

    // Navegar de vuelta a Imagen y luego regresar a Reel Studio
    $harness->call('step3_back');
    $tabsBackStep3 = $screen->getContainer()?->findByName('workspace_tabs');
    $tabsBackStep3Raw = $tabsBackStep3 ? ($tabsBackStep3->toJson()[$tabsBackStep3->getId()] ?? []) : [];
    expect($tabsBackStep3Raw['tabs_active'] ?? null)->toBe('step_image_tab');

    $harness->call('step2_next');
    $harness->call('step3_next');
    $tabsStep4 = $screen->getContainer()?->findByName('workspace_tabs');
    $tabsStep4Raw = $tabsStep4 ? ($tabsStep4->toJson()[$tabsStep4->getId()] ?? []) : [];
    expect($tabsStep4Raw['tabs_active'] ?? null)->toBe('step_review_submission_tab');

    // -------------------------------------------------------------
    // Paso 4: Enviar a revisión (Lista de revisores con review_posts)
    // -------------------------------------------------------------
    /** @var Select|null $reviewerSelect */
    $reviewerSelect = $screen->getContainer()?->findByName('reviewer_id');
    // Como hay más de un revisor (admin y reviewer), aparece el selector desplegable
    expect($reviewerSelect)->not->toBeNull();

    // Retroceder al Paso 3 y volver
    $harness->call('step4_back');
    $tabsBackStep4 = $screen->getContainer()?->findByName('workspace_tabs');
    $tabsBackStep4Raw = $tabsBackStep4 ? ($tabsBackStep4->toJson()[$tabsBackStep4->getId()] ?? []) : [];
    expect($tabsBackStep4Raw['tabs_active'] ?? null)->toBe('reel_studio_tab');

    $harness->call('step3_next');

    // Enviar a revisión seleccionando al revisor
    $harness->call('save_post', [
        'post_id' => (string) $post->id,
        'post_title' => 'Convocatoria a Becas 2026',
        'post_content' => 'Inscripciones abiertas para estudiantes de grado y posgrado.',
        'reviewer_id' => (string) $reviewer->id,
    ]);

    $postInReview = $postService->findById($post->id);
    expect($postInReview?->status)->toBe('pending_review')
        ->and($postInReview?->reviewed_by)->toBe($reviewer->id);

    // -------------------------------------------------------------
    // Paso 5: Respuesta del revisor (Rechazo -> Borrador y Aprobación -> Paso 6)
    // -------------------------------------------------------------
    // Simulamos que el revisor solicita cambios
    $this->actingAs($reviewer);
    $postService->requestChanges($post->id, $reviewer, 'Por favor corregir las fechas de entrega de formularios.');

    // El autor reabre el wizard para ver la respuesta
    $this->actingAs($publisher);
    /** @var PublishPost $authorReviewScreen */
    $authorReviewScreen = Screen::make(PublishPost::class);
    $authorReviewHarness = ScreenTestHarness::for($authorReviewScreen);
    $authorReviewHarness->render(['post_id' => (string) $post->id], shouldReset: true);

    $postChanges = $postService->findById($post->id);
    expect($postChanges?->status)->toBe('changes_requested');

    // Al presionar volver a borrador, el post pasa a draft y se activa el Paso 1
    $authorReviewHarness->call('return_to_draft');
    expect($postService->findById($post->id)?->status)->toBe('draft');

    // Simulamos que el autor corrige y el revisor aprueba
    $this->actingAs($reviewer);
    $postService->approvePost($post->id, $reviewer);
    expect($postService->findById($post->id)?->status)->toBe('published');

    // El autor abre nuevamente el wizard: se habilita la etapa 6 de generación de video
    $this->actingAs($publisher);
    /** @var PublishPost $approvedScreen */
    $approvedScreen = Screen::make(PublishPost::class);
    $approvedHarness = ScreenTestHarness::for($approvedScreen);
    $approvedHarness->render(['post_id' => (string) $post->id], shouldReset: true);

    $approvedHarness->call('step5_next');
    $tabsStep6 = $approvedScreen->getContainer()?->findByName('workspace_tabs');
    $tabsStep6Raw = $tabsStep6 ? ($tabsStep6->toJson()[$tabsStep6->getId()] ?? []) : [];
    expect($tabsStep6Raw['tabs_active'] ?? null)->toBe('step_video_generation_tab');

    // -------------------------------------------------------------
    // Paso 6: Generación de video con Remotion
    // -------------------------------------------------------------
    $approvedHarness->call('render_video');

    GenerateReelPreviewJob::putStatus($post->id, new ReelGenerationStatus(
        status: 'completed',
        version: 1,
        sceneCount: 2,
        durationSeconds: 15,
        jsonScript: $sampleScript,
        parsedScript: json_decode($sampleScript, true),
        videoFilename: 'final_becas_2026.mp4',
    ));
    $approvedHarness->call('check_video_status');

    /** @var Carousel|null $finalPlayer */
    $finalPlayer = $approvedScreen->getContainer()?->findByName('video_final_player');
    expect($finalPlayer)->not->toBeNull()
        ->and($finalPlayer?->get('visible'))->toBeTrue();

    /** @var Button|null $step6NextBtn */
    $step6NextBtn = $approvedScreen->getContainer()?->findByName('btn_step6_next');
    expect($step6NextBtn?->get('enabled'))->toBeTrue();

    // Retroceder a respuesta del revisor y volver a video
    $approvedHarness->call('step6_back');
    $tabsBackStep6 = $approvedScreen->getContainer()?->findByName('workspace_tabs');
    $tabsBackStep6Raw = $tabsBackStep6 ? ($tabsBackStep6->toJson()[$tabsBackStep6->getId()] ?? []) : [];
    expect($tabsBackStep6Raw['tabs_active'] ?? null)->toBe('step_reviewer_response_tab');

    $approvedHarness->call('step5_next');
    $approvedHarness->call('step6_next');

    // -------------------------------------------------------------
    // Paso 7: Resumen y publicar definitivamente
    // -------------------------------------------------------------
    $tabsStep7 = $approvedScreen->getContainer()?->findByName('workspace_tabs');
    $tabsStep7Raw = $tabsStep7 ? ($tabsStep7->toJson()[$tabsStep7->getId()] ?? []) : [];
    expect($tabsStep7Raw['tabs_active'] ?? null)->toBe('step_summary_publish_tab');

    // Retroceder a video y volver a resumen
    $approvedHarness->call('step7_back');
    $tabsBackStep7 = $approvedScreen->getContainer()?->findByName('workspace_tabs');
    $tabsBackStep7Raw = $tabsBackStep7 ? ($tabsBackStep7->toJson()[$tabsBackStep7->getId()] ?? []) : [];
    expect($tabsBackStep7Raw['tabs_active'] ?? null)->toBe('step_video_generation_tab');

    $approvedHarness->call('step6_next');

    // Confirmar y publicar
    $approvedHarness->call('publish_post_final');
    $finalPost = $postService->findById($post->id);
    expect($finalPost?->status)->toBe('published');
});
