<?php

declare(strict_types=1);

use App\Contracts\PostServiceInterface;
use App\Services\User\UsimUserService;
use App\UI\Requests\InitialDataRequest;
use App\UI\Screens\Post\Parts\InitialData;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;
use Tests\TestCase;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('validates required fields using InitialDataRequest and binds errors to components', function () {
    /** @var TestCase $this */
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $admin->id,
        'status' => 'draft',
    ]);

    /** @var InitialData $screen */
    $screen = Screen::make(InitialData::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render(['post_id' => $postId], shouldReset: true);

    $harness->call('on_confirm_initial_data', [
        'post_id' => $postId,
        'post_title' => '   ',
        'post_content' => '',
    ]);

    /** @var Input|null $postTitle */
    $postTitle = $screen->getContainer()?->findByName('post_title');
    /** @var Textarea|null $postContent */
    $postContent = $screen->getContainer()?->findByName('post_content');

    expect($postTitle)->not->toBeNull()
        ->and($postTitle?->get('error'))->not->toBeNull()
        ->and($postContent)->not->toBeNull()
        ->and($postContent?->get('error'))->toBe('El contenido es obligatorio.');
});

it('successfully updates post when InitialDataRequest is valid', function () {
    /** @var TestCase $this */
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $admin->id,
        'status' => 'draft',
    ]);

    /** @var InitialData $screen */
    $screen = Screen::make(InitialData::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render(['post_id' => $postId], shouldReset: true);

    $validContent = 'Contenido detallado del artículo con más de cincuenta caracteres para superar la validación inicial.';

    $harness->call('on_confirm_initial_data', [
        'post_id' => $postId,
        'post_title' => 'Featured News Article',
        'post_content' => $validContent,
    ]);

    $updatedPost = $postService->findById($postId);

    expect($updatedPost)->not->toBeNull()
        ->and($updatedPost?->title)->toBe('Featured News Article')
        ->and($updatedPost?->content)->toBe($validContent)
        ->and($updatedPost?->author?->id)->toBe($admin->id);
});

it('can be called directly with an InitialDataRequest instance', function () {
    /** @var TestCase $this */
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $admin->id,
        'status' => 'draft',
    ]);

    /** @var InitialData $screen */
    $screen = Screen::make(InitialData::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render(['post_id' => $postId], shouldReset: true);

    $validContent = 'Contenido enviado directamente mediante la instancia de InitialDataRequest con longitud suficiente.';

    $request = new InitialDataRequest();
    $request->initialize(
        screen: $screen,
        parameters: [
            'post_title' => 'Direct Call Post',
            'post_content' => $validContent,
        ]
    );
    $request->validate();

    $screen->onConfirmInitialData($postId, $request);

    expect($postService->findById($postId)?->title)->toBe('Direct Call Post');
});

it('trims title and content before validation in InitialDataRequest', function () {
    $validContent = '   Contenido suficientemente largo con más de cincuenta caracteres para validar correctamente.   ';

    $request = new InitialDataRequest();
    $request->initialize(
        screen: Screen::make(InitialData::class),
        parameters: [
            'post_title' => '   Trimmed Title Valid   ',
            'post_content' => $validContent,
        ]
    );

    $request->validate();

    expect($request->title)->toBe('Trimmed Title Valid')
        ->and($request->content)->toBe(trim($validContent));
});

it('supports clean mapped keys in InitialDataRequest', function () {
    $validContent = 'Contenido mapeado suficientemente extenso con más de cincuenta caracteres requeridos.';

    $request = new InitialDataRequest();
    $request->initialize(
        screen: Screen::make(InitialData::class),
        parameters: [
            'title' => 'Mapped Title Valid',
            'content' => $validContent,
        ]
    );

    $request->validate();

    expect($request->title)->toBe('Mapped Title Valid')
        ->and($request->content)->toBe($validContent);
});
