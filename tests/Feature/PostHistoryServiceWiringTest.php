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
use Tests\TestCase;

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
    /** @var ListItemDTO|null $firstItem */
    $firstItem = $items->first();
    expect($items)->not->toBeEmpty()
        ->and($items->count())->toBe(3)
        ->and($firstItem)->toBeInstanceOf(ListItemDTO::class)
        ->and($firstItem?->badgeStyle)->toBe(Style::INFO);

    // Filter items with search
    $filtered = $service->list('guion', ['post_id' => $postId]);
    /** @var ListItemDTO|null $firstFiltered */
    $firstFiltered = $filtered->first();
    expect($filtered)->not->toBeEmpty()
        ->and($firstFiltered?->label)->toBe('Generando guion');
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

    /** @var ListItemDTO|null $first */
    $first = $items->first();
    expect($first)->toBeInstanceOf(ListItemDTO::class)
        ->and($first?->label)->toBe('Solicitud de revisión')
        ->and($first?->badge)->toBe('Cambios solicitados')
        ->and($first?->badgeStyle)->toBe(Style::WARNING)
        ->and($first?->subtitle)->toContain('Editor')
        ->and($first?->subtitle)->toContain('Por favor cambiar la música');

    /** @var ListItemDTO|null $second */
    $second = $items->last();
    expect($second?->label)->toBe('Versión 1')
        ->and($second?->badge)->toBe('Versión generada')
        ->and($second?->badgeStyle)->toBe(Style::INFO)
        ->and($second?->subtitle)->toContain('Primera versión generada');

    // Filter by search
    $searched = $service->list('música', ['post_id' => $postId]);
    /** @var ListItemDTO|null $firstSearched */
    $firstSearched = $searched->first();
    expect($searched->count())->toBe(1)
        ->and($firstSearched?->label)->toBe('Solicitud de revisión');
});

it('wires PostHistoryService to lst_history in ReelStudio via layout and HasEditPanel', function () {
    /** @var TestCase $this */
    /** @var User $user */
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
    /** @var list<array<string, mixed>> $cfgItems */
    $cfgItems = is_array($lstCfg['items'] ?? null) ? $lstCfg['items'] : [];

    $firstItemId = $cfgItems[0]['id'] ?? '';
    expect($lstCfg['service'])->toBe(PostHistoryService::class)
        ->and($lstCfg['selection_mode'])->toBe('single')
        ->and($lstCfg['parameters'])->toBe(['post_id' => $postId])
        ->and(count($cfgItems))->toBe(1)
        ->and($cfgItems[0]['label'])->toBe('Versión 1')
        ->and($lstHistory->getSelectedIds())->toBe([is_scalar($firstItemId) ? (string) $firstItemId : '']);
});
