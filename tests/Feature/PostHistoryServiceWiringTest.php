<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PostServiceInterface;
use App\Enums\PostHistoryEventType;
use App\Models\PostHistory;
use App\Models\User;
use App\Services\Post\PostHistoryService;
use App\UI\Screens\Post\Parts\ReelStudio;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('implements Listable contract and returns ListItemDTO collections', function () {
    /** @var PostHistoryService $service */
    $service = app(PostHistoryService::class);

    expect($service)->toBeInstanceOf(Listable::class);

    $user = User::factory()->create();
    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $user->id,
        'title' => 'Post de Prueba',
        'content' => 'Contenido',
    ]);

    PostHistory::create([
        'post_id' => $postId,
        'actor_id' => $user->id,
        'event_type' => PostHistoryEventType::GENERATE_REQUESTED,
        'summary' => 'Generación solicitada',
    ]);
    PostHistory::create([
        'post_id' => $postId,
        'actor_id' => $user->id,
        'event_type' => PostHistoryEventType::GENERATING_SCRIPT,
        'summary' => 'Generando estructura con IA',
    ]);
    PostHistory::create([
        'post_id' => $postId,
        'actor_id' => $user->id,
        'event_type' => PostHistoryEventType::VERSION_GENERATED,
        'version' => 1,
        'summary' => 'Primera versión generada',
    ]);

    $items = $service->list('', ['post_id' => $postId]);
    expect($items)->not->toBeEmpty()
        ->and($items->count())->toBe(3)
        ->and($items->first())->toBeInstanceOf(ListItemDTO::class)
        ->and($items->first()->badgeStyle)->toBe(Style::INFO);

    // Filter items with search
    $filtered = $service->list('guion', ['post_id' => $postId]);
    expect($filtered)->not->toBeEmpty()
        ->and($filtered->first()->label)->toBe('Generando guion');
});

it('retrieves and formats stored post history events as ListItemDTO items', function () {
    $user = User::factory()->create(['name' => 'Editor']);
    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $user->id,
        'title' => 'Post de Prueba',
        'content' => 'Contenido',
    ]);

    PostHistory::create([
        'post_id' => $postId,
        'actor_id' => $user->id,
        'event_type' => PostHistoryEventType::VERSION_GENERATED,
        'version' => 1,
        'summary' => 'Primera versión generada',
        'reel_script' => ['scenes' => [['title' => 'Escena 1']]],
    ]);

    PostHistory::create([
        'post_id' => $postId,
        'actor_id' => $user->id,
        'event_type' => PostHistoryEventType::REVIEW_CHANGES_REQUESTED,
        'review_comment' => 'Por favor cambiar la música',
    ]);

    /** @var PostHistoryService $service */
    $service = app(PostHistoryService::class);
    $items = $service->list('', ['post_id' => $postId]);

    expect($items->count())->toBe(2);

    $first = $items->first();
    expect($first)->toBeInstanceOf(ListItemDTO::class)
        ->and($first->label)->toBe('Solicitud de revisión')
        ->and($first->badge)->toBe('Cambios solicitados')
        ->and($first->badgeStyle)->toBe(Style::WARNING)
        ->and($first->subtitle)->toContain('Editor')
        ->and($first->subtitle)->toContain('Por favor cambiar la música');

    $second = $items->last();
    expect($second->label)->toBe('Versión 1')
        ->and($second->badge)->toBe('Versión generada')
        ->and($second->badgeStyle)->toBe(Style::INFO)
        ->and($second->subtitle)->toContain('Primera versión generada')
        ->and($second->subtitle)->toContain('Generada por IA');

    // Filter by search
    $searched = $service->list('música', ['post_id' => $postId]);
    expect($searched->count())->toBe(1)
        ->and($searched->first()->label)->toBe('Solicitud de revisión');
});

it('wires PostHistoryService to lst_history in ReelStudio via layout and HasEditPanel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $user->id,
        'title' => 'Post Reel',
        'content' => 'Contenido',
    ]);

    PostHistory::create([
        'post_id' => $postId,
        'actor_id' => $user->id,
        'event_type' => PostHistoryEventType::VERSION_GENERATED,
        'version' => 1,
        'summary' => 'Borrador inicial',
    ]);

    $harness = ScreenTestHarness::for(ReelStudio::class)
        ->render(['post_id' => $postId], shouldReset: true);

    /** @var ItemList $lstHistory */
    $lstHistory = $harness->getComponent('lst_history');
    expect($lstHistory)->not->toBeNull();

    $json = $harness->getContainer()?->toJson() ?? [];
    $lstCfg = $json[$lstHistory->getId()];

    expect($lstCfg['service'])->toBe(PostHistoryService::class)
        ->and($lstCfg['selection_mode'])->toBe('single')
        ->and($lstCfg['parameters'])->toBe(['post_id' => $postId])
        ->and(count($lstCfg['items']))->toBe(1)
        ->and($lstCfg['items'][0]['label'])->toBe('Versión 1')
        ->and($lstHistory->getSelectedIds())->toBe([(string) $lstCfg['items'][0]['id']]);
});
