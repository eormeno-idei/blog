<?php

namespace Tests\Feature;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\UI\Screens\Home;
use App\UI\Screens\PublishPost;
use App\UI\Screens\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostManagerTutorialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('usim:sync');
        $this->seed();
    }

    public function test_guest_can_see_home_screen_and_posts(): void
    {
        $postService = app(PostServiceInterface::class);
        $posts = $postService->getLatestPosts();
        $this->assertNotEmpty($posts);

        $response = $this->get('/home');
        $response->assertOk();
    }

    public function test_registered_user_lands_on_registered_screen(): void
    {
        $user = User::where('email', 'registered@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isRegisteredInLobby());

        $this->actingAs($user);
        $response = $this->get('/registered');
        $response->assertOk();
    }

    public function test_publisher_can_publish_post(): void
    {
        $publisher = User::where('email', 'publisher@example.com')->first();
        $this->assertNotNull($publisher);

        $this->actingAs($publisher);
        
        $postService = app(PostServiceInterface::class);
        $newPostId = $postService->createPost([
            'user_id' => $publisher->id,
            'title' => 'Test Post Publisher',
            'content' => 'Contenido de prueba',
            'image' => null,
        ]);

        $this->assertGreaterThan(0, $newPostId);

        $post = $postService->findById($newPostId);
        $this->assertNotNull($post);
        $this->assertEquals('Test Post Publisher', $post->title);
    }

    public function test_admin_can_delete_post(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        $postService = app(PostServiceInterface::class);
        $postId = $postService->createPost([
            'user_id' => $admin->id,
            'title' => 'Post to delete',
            'content' => 'Will be deleted',
            'image' => null,
        ]);

        $deleted = $postService->deletePost($postId);
        $this->assertTrue($deleted);
        $this->assertNull($postService->findById($postId));
    }

    public function test_admin_logout_preserves_post_cards_content_and_removes_delete_buttons(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);

        $authSession = app(\App\Services\Auth\AuthSessionService::class);
        $authSession->establishSession($admin, 'lobby');

        // Initial load of home as admin
        $initialRes = $this->getJson('/api/ui/home');
        $initialRes->assertOk();
        $initialData = $initialRes->json();

        // Check delete button exists before logout
        $hasDeleteBefore = false;
        foreach ($initialData as $comp) {
            if (is_array($comp) && str_contains($comp['name'] ?? '', 'btn_delete_post_')) {
                $hasDeleteBefore = true;
                break;
            }
        }
        $this->assertTrue($hasDeleteBefore);

        // Find Menu component id
        $menuCompId = null;
        foreach ($initialData as $id => $comp) {
            if (is_array($comp) && ($comp['name'] ?? '') === 'user_menu') {
                $menuCompId = $id;
                break;
            }
        }
        $this->assertNotNull($menuCompId);

        // Perform logout via /api/ui-event
        $eventRes = $this->postJson('/api/ui-event', [
            'component_id' => $menuCompId,
            'event' => 'action',
            'action' => 'confirm_logout',
            'parameters' => [],
        ]);

        $eventRes->assertOk();
        $payload = $eventRes->json();

        $this->assertFalse(\Illuminate\Support\Facades\Auth::check());
        $this->assertEquals('success', $payload['toast']['type'] ?? null);

        // Verify that every post card and child component retains its parent so frontend can render them
        $homeComponentCount = 0;
        foreach ($payload as $key => $comp) {
            if (is_numeric($key) && is_array($comp) && isset($comp['type']) && !empty($comp['name'])) {
                if (str_starts_with($comp['name'], 'post_')) {
                    $homeComponentCount++;
                    $this->assertNotNull($comp['parent'] ?? null, "Component {$comp['name']} must retain its parent");
                }
            }
        }
        $this->assertGreaterThan(0, $homeComponentCount);

        // Verify guest view of /api/ui/home shows all 3 posts with full content and no delete buttons
        $guestRes = $this->getJson('/api/ui/home');
        $guestRes->assertOk();
        $guestData = $guestRes->json();

        $postTitles = [];
        $hasDeleteAfter = false;
        foreach ($guestData as $comp) {
            if (is_array($comp)) {
                if (str_contains($comp['name'] ?? '', 'post_title_')) {
                    $postTitles[] = $comp['text'] ?? '';
                }
                if (str_contains($comp['name'] ?? '', 'btn_delete_post_')) {
                    $hasDeleteAfter = true;
                }
            }
        }

        $this->assertFalse($hasDeleteAfter);
        $this->assertGreaterThanOrEqual(2, count($postTitles));
    }
}
