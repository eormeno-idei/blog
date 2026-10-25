<?php

use App\Contracts\PostServiceInterface;
use App\Jobs\GenerateReelPreviewJob;
use App\Services\Post\ReelStoryboardFormatter;
use App\Services\User\UsimUserService;
use App\UI\Screens\Home;
use App\UI\Screens\Post\EditPost;
use App\UI\Screens\Post\ReviewPost;
use App\UI\Screens\PublishPost;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Queue;

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

    $markdown = $formatter->format(json_encode($script), 'Hazlo más breve');

    expect($markdown)
        ->toContain('Último ajuste aplicado: "Hazlo más breve"')
        ->toContain('**Escena 1** (6s · *Portada*): **Propuesta TFG** — _Instituto de Informática_')
        ->toContain('• Beca disponible')
        ->toContain('**Escena 2** (5s · *Cierre / CTA*): **Postulate hoy**');
});

it('completes the full institutional workflow: publisher submits, reviewer requests changes, publisher refines, reviewer approves, and post expires to archived', function () {
    Queue::fake();

    $userService = app(UsimUserService::class);
    $publisher = $userService->provisionFromConfig('publisher');
    $reviewer = $userService->provisionFromConfig('reviewer');
    $postService = app(PostServiceInterface::class);

    // 1. Publisher inicia creación de propuesta y dispara generación de Reel con IA
    $this->actingAs($publisher);

    /** @var PublishPost $publishScreen */
    $publishScreen = Screen::make(PublishPost::class);
    $publishHarness = ScreenTestHarness::for($publishScreen);
    $publishHarness->render([], shouldReset: true);

    $publishHarness->call('ask_ai', ['post_content' => 'Notas de propuesta de trabajo final']);
    Queue::assertPushed(GenerateReelPreviewJob::class);

    // Simular progresos del Job en caché (generating_script -> rendering_video -> completed)
    $reflect = new ReflectionClass($publishScreen);
    $jobIdProp = $reflect->getProperty('state_active_job_id');
    $jobIdProp->setAccessible(true);
    $jobId = (string) $jobIdProp->getValue($publishScreen);

    $sampleJson = json_encode([
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

    GenerateReelPreviewJob::putStatus($jobId, [
        'status' => 'generating_script',
        'message' => 'Generando guion...',
        'detail' => 'Analizando...',
    ]);
    $publishHarness->call('check_reel_status');

    GenerateReelPreviewJob::putStatus($jobId, [
        'status' => 'rendering_video',
        'message' => 'Renderizando...',
        'detail' => 'Codificando...',
        'parsed_script' => json_decode((string) $sampleJson, true),
    ]);
    $publishHarness->call('check_reel_status');

    GenerateReelPreviewJob::putStatus($jobId, [
        'status' => 'completed',
        'version' => 1,
        'scene_count' => 1,
        'duration_seconds' => 12,
        'json_script' => $sampleJson,
        'parsed_script' => json_decode((string) $sampleJson, true),
        'video_filename' => 'preview_v1.mp4',
        'video_url' => '/storage/reels/preview_v1.mp4',
    ]);
    $publishHarness->call('check_reel_status');

    // Probar loop de refinamiento (v2) y navegación entre versiones
    $publishHarness->call('quick_refine', [
        'prompt' => 'Más corto',
        'post_content' => 'Notas de propuesta de trabajo final',
    ]);
    $jobIdV2 = (string) $jobIdProp->getValue($publishScreen);

    GenerateReelPreviewJob::putStatus($jobIdV2, [
        'status' => 'completed',
        'version' => 2,
        'scene_count' => 1,
        'duration_seconds' => 8,
        'json_script' => $sampleJson,
        'parsed_script' => json_decode((string) $sampleJson, true),
        'video_filename' => 'preview_v2.mp4',
        'video_url' => '/storage/reels/preview_v2.mp4',
        'feedback_applied' => 'Más corto',
    ]);
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

    // 4. Publisher abre EditPost, ve la alerta con la observación del revisor y reenvía a revisión
    $this->actingAs($publisher);
    /** @var EditPost $editScreen */
    $editScreen = Screen::make(EditPost::class);
    $editHarness = ScreenTestHarness::for($editScreen);
    $editHarness->render(['post_id' => $createdPost->id], shouldReset: true);

    $editHarness->assertHasComponent('lbl_reviewer_alert_body');

    $editHarness->call('save_post', [
        'post_title' => 'Propuesta Trabajo Final IA (Corregida)',
        'post_content' => 'Notas corregidas con requisitos de materias',
        'post_from' => '2026-10-25',
        'post_to' => '2026-11-15',
    ]);

    $resubmittedPost = $postService->findById($createdPost->id);
    expect($resubmittedPost?->status)->toBe('pending_review')
        ->and($resubmittedPost?->title)->toBe('Propuesta Trabajo Final IA (Corregida)');

    // 5. Reviewer aprueba la publicación -> pasa a published
    $this->actingAs($reviewer);
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
    \Illuminate\Support\Facades\Process::fake([
        '*' => \Illuminate\Support\Facades\Process::result(output: 'Rendered successfully', exitCode: 0),
    ]);

    $validJson = json_encode([
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

    \App\Ai\Agents\PostAgent::fake([
        "```json\n{$validJson}\n```",
        'no-es-json',
    ]);

    $job = new GenerateReelPreviewJob(
        jobId: 'test-job-1',
        notes: 'Notas de prueba',
        previousJson: $validJson,
        userFeedback: 'Hazlo más corto',
        versionNumber: 2
    );

    $job->handle();

    $status = GenerateReelPreviewJob::getStatus('test-job-1');
    expect($status)->not->toBeNull()
        ->and($status['status'])->toBe('completed')
        ->and($status['version'])->toBe(2)
        ->and($status['feedback_applied'])->toBe('Hazlo más corto');

    // Caso de fallo por JSON inválido
    $badJob = new GenerateReelPreviewJob(
        jobId: 'test-job-fail',
        notes: 'Notas',
    );
    $badJob->handle();

    $failStatus = GenerateReelPreviewJob::getStatus('test-job-fail');
    expect($failStatus['status'] ?? null)->toBe('failed');
});

