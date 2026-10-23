<?php

// @usim: feature="admin", type="test"

use App\Contracts\PostServiceInterface;
use App\Http\Requests\PostRequest;
use App\Services\User\UsimUserService;
use App\UI\Screens\Post\EditPost;
use Idei\Usim\Components\Input;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('validates required fields using PostRequest in EditPost and binds errors', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $admin->id,
        'title' => 'Original Title',
        'content' => 'Original Content',
        'image' => null,
    ]);

    /** @var EditPost $screen */
    $screen = Screen::make(EditPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render(['post_id' => $postId], shouldReset: true);

    $initialCount = $postService->getLatestPosts()->count();

    $harness->call('save_post', [
        'post_title' => '',
        'post_content' => '',
    ]);

    expect($postService->getLatestPosts()->count())->toBe($initialCount);

    /** @var Input|null $postTitle */
    $postTitle = $screen->getContainer()?->findByName('post_title');
    /** @var Input|null $postContent */
    $postContent = $screen->getContainer()?->findByName('post_content');

    expect($postTitle)->not->toBeNull()
        ->and($postTitle?->get('error'))->toBe('El título es obligatorio.')
        ->and($postContent)->not->toBeNull()
        ->and($postContent?->get('error'))->toBe('El contenido es obligatorio.');
});

it('saves post when valid PostRequest is passed in EditPost', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $admin->id,
        'title' => 'Initial Title',
        'content' => 'Initial Content',
        'image' => null,
    ]);

    /** @var EditPost $screen */
    $screen = Screen::make(EditPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render(['post_id' => $postId], shouldReset: true);

    $harness->call('save_post', [
        'post_title' => 'Updated Post Title',
        'post_content' => 'Updated Post Content',
    ]);

    expect($postService->getLatestPosts()->firstWhere('title', 'Updated Post Title'))->not->toBeNull();
});

it('can call onSavePost directly with PostRequest in EditPost', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    $postService = app(PostServiceInterface::class);
    $postId = $postService->createPost([
        'user_id' => $admin->id,
        'title' => 'Pre-edit Title',
        'content' => 'Pre-edit Content',
        'image' => null,
    ]);

    /** @var EditPost $screen */
    $screen = Screen::make(EditPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render(['post_id' => $postId], shouldReset: true);

    $request = PostRequest::fromArray([
        'post_title' => 'Direct Edit Post',
        'post_content' => 'Direct Edit Content',
    ], $screen);

    $screen->onSavePost($request);

    expect($postService->getLatestPosts()->firstWhere('title', 'Direct Edit Post'))->not->toBeNull();
});

