<?php

use App\Contracts\PostServiceInterface;
use App\Http\Requests\PostRequest;
use App\Services\User\UsimUserService;
use App\UI\Screens\PublishPost;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Screen;
use Idei\Usim\Support\RoleAndPermissionSyncService;
use Idei\Usim\Testing\ScreenTestHarness;

beforeEach(function () {
    app(RoleAndPermissionSyncService::class)->sync();
});

it('validates required fields using PostRequest and binds errors to components', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    /** @var PublishPost $screen */
    $screen = Screen::make(PublishPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render([], shouldReset: true);

    $harness->call('save_post', [
        'post_title' => '   ',
        'post_content' => '',
    ]);

    $postService = app(PostServiceInterface::class);
    expect($postService->getLatestPosts()->where('title', ''))->toBeEmpty();

    /** @var Input|null $postTitle */
    $postTitle = $screen->getContainer()?->findByName('post_title');
    /** @var Textarea|null $postContent */
    $postContent = $screen->getContainer()?->findByName('post_content');

    expect($postTitle)->not->toBeNull()
        ->and($postTitle?->get('error'))->toBe('El título es obligatorio.')
        ->and($postContent)->not->toBeNull()
        ->and($postContent?->get('error'))->toBe('El contenido es obligatorio.');
});

it('successfully creates post when PostRequest is valid', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    /** @var PublishPost $screen */
    $screen = Screen::make(PublishPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render([], shouldReset: true);

    $harness->call('save_post', [
        'post_title' => 'Featured News Article',
        'post_content' => 'Content of the article',
    ]);

    $postService = app(PostServiceInterface::class);
    $createdPost = $postService->getLatestPosts()->firstWhere('title', 'Featured News Article');

    expect($createdPost)->not->toBeNull()
        ->and($createdPost->content)->toBe('Content of the article')
        ->and($createdPost->user_id)->toBe($admin->id);
});

it('can be called directly with a PostRequest instance', function () {
    $admin = app(UsimUserService::class)->provisionFromConfig('admin');
    $this->actingAs($admin);

    /** @var PublishPost $screen */
    $screen = Screen::make(PublishPost::class);
    $harness = ScreenTestHarness::for($screen);
    $harness->render([], shouldReset: true);

    $request = PostRequest::fromArray([
        'post_title' => 'Direct Call Post',
        'post_content' => 'Content from direct request',
    ], $screen);

    $screen->onSavePost($request);

    $postService = app(PostServiceInterface::class);
    expect($postService->getLatestPosts()->where('title', 'Direct Call Post'))->not->toBeEmpty();
});

it('trims title and content before validation in PostRequest', function () {
    $request = PostRequest::fromArray([
        'post_title' => '   Trimmed Title   ',
        'post_content' => '   Trimmed Content   ',
    ]);

    $validated = $request->validate();

    expect($request->title())->toBe('Trimmed Title')
        ->and($request->content())->toBe('Trimmed Content')
        ->and($validated['post_title'])->toBe('Trimmed Title')
        ->and($validated['post_content'])->toBe('Trimmed Content');
});

it('supports clean mapped keys in PostRequest', function () {
    $request = PostRequest::fromArray([
        'title' => 'Mapped Title',
        'content' => 'Mapped Content',
    ]);

    $validated = $request->validate();

    expect($request->title())->toBe('Mapped Title')
        ->and($request->content())->toBe('Mapped Content')
        ->and($validated['post_title'])->toBe('Mapped Title')
        ->and($validated['post_content'])->toBe('Mapped Content');
});
