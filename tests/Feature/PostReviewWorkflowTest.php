<?php

declare(strict_types=1);

use App\Ai\Agents\PostAgent;
use App\Contracts\PostHistoryServiceInterface;
use App\Contracts\PostServiceInterface;
use App\DTOs\ReelGenerationStatus;
use App\Jobs\GenerateReelPreviewJob;
use App\Jobs\GenerateReelStructureJob;
use App\Services\Post\ReelStoryboardFormatter;
use App\Services\User\UsimUserService;
use App\UI\Screens\Home;
use App\UI\Screens\Post\Parts\DateTimeData;
use App\UI\Screens\Post\Parts\InitialData;
use App\UI\Screens\Post\Parts\ReelStudio;
use App\UI\Screens\Post\ReviewPost;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Timer;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\Auth;
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
    $historyService = app(PostHistoryServiceInterface::class);

    // 1. Publisher presiona "Publicar Post" en Home -> se crea inmediatamente el borrador en BBDD
    $this->actingAs($publisher);

    $homePublisherHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    $homePublisherHarness->call('go_to_publish');

    $draftPost = $postService->getLatestPosts($publisher)->first();
    expect($draftPost)->not->toBeNull()
        ->and($draftPost?->statusValue())->toBe('draft');

    $postId = (int) $draftPost?->id;

    // Paso 1: Datos iniciales (InitialData)
    /** @var InitialData $initialDataScreen */
    $initialDataScreen = Screen::make(InitialData::class);
    $initialHarness = ScreenTestHarness::for($initialDataScreen);
    $initialHarness->render(['post_id' => $postId], shouldReset: true);

    $validContent = 'Notas detalladas de propuesta de trabajo final sobre inteligencia artificial en el instituto.';
    $initialHarness->call('on_confirm_initial_data', [
        'post_id' => $postId,
        'post_title' => 'Propuesta Trabajo Final IA',
        'post_content' => $validContent,
    ]);
    expect($postService->findById($postId)?->title)->toBe('Propuesta Trabajo Final IA');

    // Paso 2: Fechas de vigencia (DateTimeData)
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

    // Paso 3: Reel Studio (generación de primera estructura con IA)
    /** @var ReelStudio $reelStudioScreen */
    $reelStudioScreen = Screen::make(ReelStudio::class);
    $reelStudioHarness = ScreenTestHarness::for($reelStudioScreen);
    $reelStudioHarness->render(['post_id' => $postId], shouldReset: true);

    $reelStudioHarness->click('btn_generate_first_structure', [
        'postId' => $postId,
        'txt_initial_instructions' => 'Enfocar en TFG',
    ]);
    Queue::assertPushed(GenerateReelStructureJob::class);

    /** @var Timer|null $pollTimer */
    $pollTimer = $reelStudioScreen->getContainer()?->findByName('tmr_reel_poll');
    expect($pollTimer?->get('enabled'))->toBeTrue();

    /** @var array<string, mixed> $sampleScript */
    $sampleScript = [
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
    ];
    $sampleJson = (string) json_encode($sampleScript);

    $reqEvent = $historyService->getLatestReelEvent($postId);
    if ($reqEvent !== null) {
        $this->travel(1)->seconds();
        $genEvent = $historyService->createGeneratingReelScriptEvent($reqEvent);
        $this->travel(1)->seconds();
        $historyService->createCompletedReelScriptEvent($genEvent, $sampleScript);
    }

    $reelStudioHarness->call('check_reel_status', ['post_id' => $postId]);
    /** @var Timer|null $pollTimerAfter */
    $pollTimerAfter = $reelStudioScreen->getContainer()?->findByName('tmr_reel_poll');
    expect($pollTimerAfter?->get('enabled'))->toBeFalse();

    // 2. Publisher envía la propuesta a revisión
    $postService->updatePost($postId, [
        'status' => 'pending_review',
        'ai_script' => $sampleJson,
        'reel_video' => 'preview_v2.mp4',
    ], $publisher);

    $createdPost = $postService->findById($postId);
    expect($createdPost)->not->toBeNull()
        ->and($createdPost?->statusValue())->toBe('pending_review')
        ->and($createdPost?->reelVideo)->toBe('preview_v2.mp4')
        ->and($createdPost?->aiScript)->toBe($sampleJson);

    // 3. Reviewer ingresa a Home, ve el post y entra a ReviewPost
    $this->actingAs($reviewer);
    $homeHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    $homeHarness->assertHasComponent("btn_review_post_{$postId}");

    /** @var ReviewPost $reviewScreen */
    $reviewScreen = Screen::make(ReviewPost::class);
    $reviewHarness = ScreenTestHarness::for($reviewScreen);
    $reviewHarness->render(['post_id' => $postId], shouldReset: true);

    // Validación de comentario vacío al pedir modificaciones
    $reviewScreen->onRequestChanges(['reviewer_comment' => '   ']);
    expect($postService->findById($postId)?->statusValue())->toBe('pending_review');

    // Reviewer deja una observación y devuelve el post a changes_requested
    $reviewScreen->onRequestChanges([
        'reviewer_comment' => 'Falta agregar el requisito de materias aprobadas en la escena 1.',
    ]);

    $reviewedPost = $postService->findById($postId);
    expect($reviewedPost?->statusValue())->toBe('changes_requested')
        ->and($reviewedPost?->reviewerComment)->toBe('Falta agregar el requisito de materias aprobadas en la escena 1.')
        ->and($reviewedPost?->reviewedBy?->id)->toBe($reviewer->id);

    // 4. Publisher presiona "Corregir" en Home y actualiza el título sin duplicar el post
    $this->actingAs($publisher);
    $postsCountBeforeEdit = $postService->getLatestPosts($publisher)->count();

    $homeEditHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    $homeEditHarness->call('edit_post', ['postId' => $postId]);

    /** @var PublishPost $editScreen */
    $editScreen = Screen::make(PublishPost::class);
    $editHarness = ScreenTestHarness::for($editScreen);
    $editHarness->withQuery(['post_id' => (string) $postId])->render(['post_id' => $postId], shouldReset: true);

    expect($postService->getLatestPosts($publisher)->count())->toBe($postsCountBeforeEdit);

    $postService->updatePost($postId, [
        'title' => 'Propuesta Trabajo Final IA (Corregida)',
        'content' => 'Notas corregidas con requisitos de materias aprobadas para el proyecto final.',
        'status' => 'pending_review',
    ], $publisher);

    expect($postService->getLatestPosts($publisher)->count())->toBe($postsCountBeforeEdit);
    $resubmittedPost = $postService->findById($postId);
    expect($resubmittedPost?->statusValue())->toBe('pending_review')
        ->and($resubmittedPost?->title)->toBe('Propuesta Trabajo Final IA (Corregida)');

    // 5. Reviewer aprueba la publicación -> pasa a published
    $this->actingAs($reviewer);
    /** @var ReviewPost $reviewScreen2 */
    $reviewScreen2 = Screen::make(ReviewPost::class);
    ScreenTestHarness::for($reviewScreen2)->render(['post_id' => $postId], shouldReset: true);
    $reviewScreen2->onApprovePost([]);

    $approvedPost = $postService->findById($postId);
    expect($approvedPost?->statusValue())->toBe('published')
        ->and($approvedPost?->reviewerComment)->toBeNull();

    // Verificamos que Home lee correctamente el estado 'published' y muestra el badge '🟢 Publicado'
    Auth::logout();
    $homeGuestHarness = ScreenTestHarness::for(Home::class)->render(shouldReset: true);
    /** @var NewLabel|null $statusBadge */
    $statusBadge = $homeGuestHarness->findComponent("post_status_{$postId}");
    expect($statusBadge)->not->toBeNull()
        ->and($statusBadge?->get('text'))->toBe('🟢 Publicado');

    // 6. Una vez vencida la fecha 'to', el post pasa automáticamente a 'archived'
    $this->travel(25)->days();

    $expiredPost = $postService->findById($postId);
    expect($expiredPost?->statusValue())->toBe('archived');
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

    /** @var ReelGenerationStatus|null $status */
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

    /** @var ReelGenerationStatus|null $failStatus */
    $failStatus = GenerateReelPreviewJob::getStatus($failPostId);
    expect($failStatus?->status)->toBe('failed');
});
