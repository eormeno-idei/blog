<?php

use App\Ai\Agents\PostAgent;
use App\Contracts\PostServiceInterface;
use App\DTOs\ReelGenerationStatus;
use App\Jobs\GenerateReelPreviewJob;
use App\Services\Post\ReelStoryboardFormatter;
use App\Services\User\UsimUserService;
use App\UI\Screens\Home;
use App\UI\Screens\Post\ReviewPost;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Carousel;
use Idei\Usim\Components\Timer;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('formats storyboard markdown cleanly in ReelStoryboardFormatter', function () {
    $formatter = app(ReelStoryboardFormatter::class);

    expect($formatter->format(null))->toBe('Sin detalle de escenas disponible.')
        ->and($formatter->format('invalid-json'))->toBe('Sin detalle de escenas disponible.');

    $script = [
        'scenes' => [
            [
                'scene_index' => 1,
                'layout_template' => 'title_hero',
                'duration_seconds' => 6,
                'text_content' => [
                    'headline' => 'Propuesta TFG',
                    'subheadline' => 'Instituto de Informática',
                    'body' => ['Beca disponible', 'Inscripción abierta'],
                ],
            ],
            [
                'scene_index' => 2,
                'layout_template' => 'call_to_action',
                'duration_seconds' => 5,
                'text_content' => [
                    'headline' => 'Postulate hoy',
                    'subheadline' => null,
                    'body' => [],
                ],
            ],
        ],
    ];

    $markdown = $formatter->format((string) json_encode($script), 'Hazlo más breve');

    expect($markdown)
        ->toContain('Último ajuste aplicado: "Hazlo más breve"')
        ->toContain('**Escena 1** (6s · *Portada*): **Propuesta TFG** — _Instituto de Informática_')
        ->toContain('• Beca disponible')
        ->toContain('**Escena 2** (5s · *Cierre / CTA*): **Postulate hoy**');
});

it('completes the full institutional workflow: publisher submits, reviewer requests changes, publisher refines, reviewer approves, and post expires to archived', function () {
    /** @var TestCase $this */
    Queue::fake();

    $userService = app(UsimUserService::class);
    $publisher = $userService->provisionFromConfig('publisher');
    $reviewer = $userService->provisionFromConfig('reviewer');
    $postService = app(PostServiceInterface::class);

    // 1. Publisher presiona "Publicar Post" en Home o ingresa a PublishPost -> se crea inmediatamente el borrador en BBDD
    $this->actingAs($publisher);

    $homePublisherHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    $homePublisherHarness->call('go_to_publish');

    $draftPost = $postService->getLatestPosts()->firstWhere('author_id', $publisher->id);
    expect($draftPost)->not->toBeNull()
        ->and($draftPost->status)->toBe('draft');

    /** @var PublishPost $publishScreen */
    $publishScreen = Screen::make(PublishPost::class);
    $publishHarness = ScreenTestHarness::for($publishScreen);
    $publishHarness->render(['post_id' => $draftPost->id], shouldReset: true);

    // No debe existir el botón "Cancelar", "Enviar a Revisión" inicia deshabilitado, y la pestaña Estudio de Reel IA inicia deshabilitada
    /** @var Button|null $initialSaveBtn */
    $initialSaveBtn = $publishScreen->getContainer()?->findByName('btn_save_post');
    expect($publishScreen->getContainer()?->findByName('btn_cancel_post'))->toBeNull()
        ->and($initialSaveBtn?->get('enabled'))->toBeFalse();

    // Al modificar el título, se guarda inmediatamente en la BBDD
    $publishHarness->call('on_title_saved', ['value' => 'Título en Vivo']);
    expect($postService->findById($draftPost->id)?->title)->toBe('Título en Vivo');

    $tabsContainer = $publishScreen->getContainer()?->findByName('workspace_tabs');
    $tabsRaw = $tabsContainer ? ($tabsContainer->toJson()[$tabsContainer->getId()] ?? []) : [];
    $tabsConfig = $tabsRaw['tabs'] ?? [];
    $studioTabBefore = collect($tabsConfig)->firstWhere('id', 'reel_studio_tab');
    expect($studioTabBefore['disabled'] ?? null)->toBeTrue()
        ->and($tabsRaw['tabs_active'] ?? null)->toBe('form_tab');

    // El reproductor de preview debe ser vertical (9:16 -> 315x560)
    /** @var Carousel|null $playerComponent */
    $playerComponent = $publishScreen->getContainer()?->findByName('reel_preview_player');
    expect($playerComponent?->get('width'))->toBe('315px')
        ->and($playerComponent?->get('height'))->toBe('560px');

    $publishHarness->call('ask_ai', ['post_content' => 'Notas de propuesta de trabajo final']);
    Queue::assertPushed(GenerateReelPreviewJob::class);

    // El estado del procesamiento se guarda en la tabla posts (ai_job_status)
    expect($postService->findById($draftPost->id)?->ai_job_status)->toBe('generating_script');

    // Al iniciar la generación, la pestaña Estudio de Reel IA se habilita y pasa a estar activa
    $tabsContainerAfter = $publishScreen->getContainer()?->findByName('workspace_tabs');
    $tabsRawAfter = $tabsContainerAfter ? ($tabsContainerAfter->toJson()[$tabsContainerAfter->getId()] ?? []) : [];
    $tabsConfigAfter = $tabsRawAfter['tabs'] ?? [];
    $studioTabAfter = collect($tabsConfigAfter)->firstWhere('id', 'reel_studio_tab');
    expect($studioTabAfter['disabled'] ?? null)->toBeFalse()
        ->and($tabsRawAfter['tabs_active'] ?? null)->toBe('reel_studio_tab');

    // Simular que el usuario cierra la ventana mientras está en rendering_video y vuelve a abrir el post más tarde
    $postId = (int) $draftPost->id;

    $sampleJson = (string) json_encode([
        'metadata' => ['autor' => 'UNSJ', 'tema' => 'TFG', 'duracion_total_segundos' => 12],
        'scenes' => [
            [
                'scene_index' => 1,
                'layout_template' => 'bullet_points',
                'duration_seconds' => 12,
                'text_content' => [
                    'headline' => 'Trabajo Final',
                    'subheadline' => 'Convocatoria 2026',
                    'body' => ['Punto 1', 'Punto 2'],
                ],
            ],
        ],
    ]);

    GenerateReelPreviewJob::putStatus($postId, new ReelGenerationStatus(
        status: 'generating_script',
        message: 'Generando guion...',
        detail: 'Analizando...',
    ));
    $publishHarness->call('check_reel_status');

    GenerateReelPreviewJob::putStatus($postId, new ReelGenerationStatus(
        status: 'rendering_video',
        message: 'Renderizando...',
        detail: 'Codificando...',
        parsedScript: json_decode($sampleJson, true),
    ));

    // Si el usuario reabre PublishPost mientras está en rendering_video, retoma el estado desde la BBDD
    /** @var PublishPost $reopenedScreen */
    $reopenedScreen = Screen::make(PublishPost::class);
    ScreenTestHarness::for($reopenedScreen)->render(['post_id' => $draftPost->id], shouldReset: true);
    /** @var Timer|null $reopenedPollTimer */
    $reopenedPollTimer = $reopenedScreen->getContainer()?->findByName('tmr_reel_poll');
    expect($reopenedPollTimer?->get('enabled'))->toBeTrue();

    $publishHarness->call('check_reel_status');

    GenerateReelPreviewJob::putStatus($postId, new ReelGenerationStatus(
        status: 'completed',
        version: 1,
        sceneCount: 1,
        durationSeconds: 12,
        jsonScript: $sampleJson,
        parsedScript: json_decode($sampleJson, true),
        videoFilename: 'preview_v1.mp4',
        videoUrl: '/storage/reels/preview_v1.mp4',
    ));
    $publishHarness->call('check_reel_status');

    // Una vez completada al menos una generación de IA, el botón "Enviar a Revisión" queda habilitado
    /** @var Button|null $enabledSaveBtn */
    $enabledSaveBtn = $publishScreen->getContainer()?->findByName('btn_save_post');
    expect($enabledSaveBtn?->get('enabled'))->toBeTrue();

    // Probar loop de refinamiento (v2) y navegación entre versiones
    $publishHarness->call('quick_refine', [
        'prompt' => 'Más corto',
        'post_content' => 'Notas de propuesta de trabajo final',
    ]);

    GenerateReelPreviewJob::putStatus($postId, new ReelGenerationStatus(
        status: 'completed',
        version: 2,
        sceneCount: 1,
        durationSeconds: 8,
        jsonScript: $sampleJson,
        parsedScript: json_decode((string) $sampleJson, true),
        videoFilename: 'preview_v2.mp4',
        videoUrl: '/storage/reels/preview_v2.mp4',
        feedbackApplied: 'Más corto',
    ));
    $publishHarness->call('check_reel_status');

    $publishHarness->call('select_prev_version');
    $publishHarness->call('select_next_version');

    // 2. Publisher envía la propuesta a revisión con fechas from y to
    $publishHarness->call('save_post', [
        'post_title' => 'Propuesta Trabajo Final IA',
        'post_content' => 'Notas de propuesta de trabajo final',
        'post_from' => '2026-10-25',
        'post_to' => '2026-11-15',
    ]);

    $createdPost = $postService->getLatestPosts()->firstWhere('title', 'Propuesta Trabajo Final IA');
    expect($createdPost)->not->toBeNull()
        ->and($createdPost->status)->toBe('pending_review')
        ->and($createdPost->reel_video)->toBe('preview_v2.mp4')
        ->and($createdPost->ai_script)->toBe($sampleJson);

    // 3. Reviewer ingresa a Home, ve el post y entra a ReviewPost
    $this->actingAs($reviewer);
    $homeHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    $homeHarness->assertHasComponent("btn_review_post_{$createdPost->id}");

    /** @var ReviewPost $reviewScreen */
    $reviewScreen = Screen::make(ReviewPost::class);
    $reviewHarness = ScreenTestHarness::for($reviewScreen);
    $reviewHarness->render(['post_id' => $createdPost->id], shouldReset: true);

    // Validación de comentario vacío al pedir modificaciones
    $reviewScreen->onRequestChanges(['reviewer_comment' => '   ']);
    expect($postService->findById($createdPost->id)?->status)->toBe('pending_review');

    // Reviewer deja una observación y devuelve el post a changes_requested
    $reviewScreen->onRequestChanges([
        'reviewer_comment' => 'Falta agregar el requisito de materias aprobadas en la escena 1.',
    ]);

    $reviewedPost = $postService->findById($createdPost->id);
    expect($reviewedPost?->status)->toBe('changes_requested')
        ->and($reviewedPost?->reviewer_comment)->toBe('Falta agregar el requisito de materias aprobadas en la escena 1.')
        ->and((int) $reviewedPost?->reviewed_by)->toBe($reviewer->id);

    // 4. Publisher presiona "Corregir" en Home o accede directamente por URL (?post_id=...)
    // y no se crea un "Borrador sin título" extra ni se duplica el post al enviarlo a revisión
    $this->actingAs($publisher);
    $postsCountBeforeEdit = $postService->getLatestPosts()->count();

    $homeEditHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    $homeEditHarness->call('edit_post', ['post_id' => $createdPost->id]);

    /** @var PublishPost $editScreen */
    $editScreen = Screen::make(PublishPost::class);
    $editHarness = ScreenTestHarness::for($editScreen);
    // Simulando acceso/recarga con parámetro en la URL (?post_id=...)
    $editHarness->withQuery(['post_id' => (string) $createdPost->id])->render([], shouldReset: true);

    expect($postService->getLatestPosts()->count())->toBe($postsCountBeforeEdit);
    $editHarness->assertHasComponent('lbl_reviewer_alert_body');
    /** @var Button|null $editSaveBtn */
    $editSaveBtn = $editScreen->getContainer()?->findByName('btn_save_post');
    expect($editSaveBtn?->get('enabled'))->toBeTrue();

    $editHarness->call('save_post', [
        'post_id' => (string) $createdPost->id,
        'post_title' => 'Propuesta Trabajo Final IA (Corregida)',
        'post_content' => 'Notas corregidas con requisitos de materias',
        'post_from' => '2026-10-25',
        'post_to' => '2026-11-15',
    ]);

    expect($postService->getLatestPosts()->count())->toBe($postsCountBeforeEdit);
    $resubmittedPost = $postService->findById($createdPost->id);
    expect($resubmittedPost?->status)->toBe('pending_review')
        ->and($resubmittedPost?->title)->toBe('Propuesta Trabajo Final IA (Corregida)');

    // 5. Reviewer aprueba la publicación -> pasa a published
    $this->actingAs($reviewer);
    /** @var ReviewPost $reviewScreen2 */
    $reviewScreen2 = Screen::make(ReviewPost::class);
    ScreenTestHarness::for($reviewScreen2)->render(['post_id' => $createdPost->id], shouldReset: true);
    $reviewScreen2->onApprovePost([]);

    $approvedPost = $postService->findById($createdPost->id);
    expect($approvedPost?->status)->toBe('published')
        ->and($approvedPost?->reviewer_comment)->toBeNull();

    // 6. Una vez vencida la fecha 'to', el post pasa automáticamente a 'archived'
    $postService->updatePost($createdPost->id, [
        'to' => now()->subDay()->toDateTimeString(),
    ]);

    $expiredPost = $postService->findById($createdPost->id);
    expect($expiredPost?->status)->toBe('archived');
});

it('executes GenerateReelPreviewJob handle for initial generation, refinement, and failure handling', function () {
    Process::fake([
        '*' => Process::result(output: 'Rendered successfully', exitCode: 0),
    ]);

    $validJson = (string) json_encode([
        'metadata' => ['autor' => 'UNSJ', 'tema' => 'Test', 'duracion_total_segundos' => 10],
        'scenes' => [
            [
                'scene_index' => 1,
                'layout_template' => 'title_hero',
                'duration_seconds' => 10,
                'text_content' => ['headline' => 'Hola', 'subheadline' => null, 'body' => []],
            ],
        ],
    ]);

    PostAgent::fake([
        "```json\n{$validJson}\n```",
        'no-es-json',
    ]);

    $userService = app(UsimUserService::class);
    $user = $userService->provisionFromConfig('publisher');

    $postService = app(PostServiceInterface::class);
    $testPostId = $postService->createPost([
        'user_id' => $user->id,
        'title' => 'Test Post',
        'content' => 'Notas de prueba',
    ]);
    $failPostId = $postService->createPost([
        'user_id' => $user->id,
        'title' => 'Test Post Fail',
        'content' => 'Notas',
    ]);

    $job = new GenerateReelPreviewJob(
        postId: $testPostId,
        notes: 'Notas de prueba',
        previousJson: $validJson,
        userFeedback: 'Hazlo más corto',
        versionNumber: 2
    );

    $job->handle();

    $status = GenerateReelPreviewJob::getStatus($testPostId);
    expect($status)->not->toBeNull()
        ->and($status?->status)->toBe('completed')
        ->and($status?->version)->toBe(2)
        ->and($status?->feedbackApplied)->toBe('Hazlo más corto');

    // Caso de fallo por JSON inválido
    $badJob = new GenerateReelPreviewJob(
        postId: $failPostId,
        notes: 'Notas',
    );
    $badJob->handle();

    $failStatus = GenerateReelPreviewJob::getStatus($failPostId);
    expect($failStatus?->status)->toBe('failed');
});
