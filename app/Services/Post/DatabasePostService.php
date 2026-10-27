<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use App\Services\User\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DatabasePostService implements PostServiceInterface
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * @return Collection<int, \stdClass>
     */
    public function getLatestPosts(int $ownerId = 0, string $filter = ''): Collection
    {
        $this->archiveExpiredPosts();

        $isPublisher = $this->userService->currentUserHasRole('publisher');

        if ($isPublisher) {
            $ownerId = Auth::id();
        }

        $query = DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('users as reviewers', 'posts.reviewed_by', '=', 'reviewers.id')
            ->select(
                'posts.*',
                'users.id as author_id',
                'users.name as author_name',
                'reviewers.name as reviewer_name'
            );

        if ($ownerId > 0) {
            $query->where('posts.user_id', $ownerId);
        }

        if ($filter !== '') {
            $query->where(function ($q) use ($filter) {
                $q->where('posts.title', 'like', "%{$filter}%")
                    ->orWhere('posts.content', 'like', "%{$filter}%");
            });
        }

        return $query
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->get();
    }

    public function findById(int $id): ?object
    {
        $this->archiveExpiredPosts();

        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('users as reviewers', 'posts.reviewed_by', '=', 'reviewers.id')
            ->select(
                'posts.*',
                'users.id as author_id',
                'users.name as author_name',
                'reviewers.name as reviewer_name'
            )
            ->where('posts.id', $id)
            ->first();
    }

    public function createPost(array $data): int
    {
        return DB::table('posts')->insertGetId([
            'user_id' => $data['user_id'],
            'title' => $this->nextPostTitle($data['user_id']),
            'content' => self::defaultPostTemplate()['content'],
            'image' => $data['image'] ?? null,
            'background_color' => $data['background_color'] ?? '#32637b',
            'text_color' => $data['text_color'] ?? '#000000',
            'background_image' => $data['background_image'] ?? null,
            'aspect_ratio' => $data['aspect_ratio'] ?? '16:9',
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'status' => $data['status'] ?? 'pending_review',
            'ai_script' => $data['ai_script'] ?? null,
            'reel_video' => $data['reel_video'] ?? null,
            'ai_job_status' => $data['ai_job_status'] ?? 'idle',
            'ai_job_payload' => isset($data['ai_job_payload']) && is_array($data['ai_job_payload'])
                ? json_encode($data['ai_job_payload'], JSON_UNESCAPED_UNICODE)
                : ($data['ai_job_payload'] ?? null),
            'reviewer_comment' => $data['reviewer_comment'] ?? null,
            'reviewed_by' => $data['reviewed_by'] ?? null,
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextPostTitle(int $userId): string
    {
        $defaultTitle = self::defaultPostTemplate()['title'];
        $titlesStartingWithDefault = DB::table('posts')
            ->where('user_id', $userId)
            ->where('title', 'like', "{$defaultTitle}%")
            ->pluck('title')
            ->toArray();
        // Extract numbers from titles and find the next available number
        $maxNumber = 0;
        foreach ($titlesStartingWithDefault as $title) {
            if (preg_match('/^' . preg_quote($defaultTitle, '/') . ' (\d+)$/', $title, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }
        $maxNumber++;
        return $maxNumber > 0 ? "{$defaultTitle} {$maxNumber}" : $defaultTitle;
    }

    /**
     * @inheritDoc
     */
    public function isCurrentUserOwner(\stdClass $post): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        return (int) $post->author_id === (int) $user->id;
    }

    public function updatePost(int $id, array $data): bool
    {
        $allowedKeys = [
            'title',
            'content',
            'image',
            'background_color',
            'text_color',
            'background_image',
            'aspect_ratio',
            'from',
            'to',
            'status',
            'ai_script',
            'reel_video',
            'ai_job_status',
            'ai_job_payload',
            'reviewer_comment',
            'reviewed_by',
            'reviewed_at',
        ];

        $payload = array_intersect_key($data, array_flip($allowedKeys));
        if (isset($payload['ai_job_payload']) && is_array($payload['ai_job_payload'])) {
            $payload['ai_job_payload'] = json_encode($payload['ai_job_payload'], JSON_UNESCAPED_UNICODE);
        }
        $payload['updated_at'] = now();

        return DB::table('posts')
            ->where('id', $id)
            ->update($payload) > 0;
    }

    public function requestChanges(int $postId, int $reviewerId, string $comment): bool
    {
        return $this->updatePost($postId, [
            'status' => 'changes_requested',
            'reviewer_comment' => trim($comment),
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    public function approvePost(int $postId, int $reviewerId): bool
    {
        return $this->updatePost($postId, [
            'status' => 'published',
            'reviewer_comment' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    public function archiveExpiredPosts(): int
    {
        return DB::table('posts')
            ->where('status', 'published')
            ->whereNotNull('to')
            ->where('to', '<', now())
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);
    }

    public function deletePost(int $id): bool
    {
        $user = Auth::user();
        if (! $user) {
            throw new \RuntimeException("No authenticated user found.");
        }

        $post = $this->findById($id);
        if (! $post) {
            throw new \RuntimeException("Post with ID {$id} not found.");
        }

        if ($post->status === 'published') {
            throw new \RuntimeException("Cannot delete a published post.");
        }

        $isOwner = (int) $post->author_id === (int) $user->id;
        $isAdmin = $this->userService->currentUserHasRole('admin');

        if (! $isOwner && ! $isAdmin) {
            throw new \RuntimeException("User does not have permission to delete this post.");
        }

        return DB::table('posts')->where('id', $id)->delete() > 0;
    }

    /**
     * Get the default template for a new post.
     * 
     * @return array{title: string, content: string}
     */
    private static function defaultPostTemplate(): array
    {
        return [
            'title' => 'Borrador sin título',
            'content' => <<<'EOT'
Escribe aquí las notas o información del evento/proyecto que quieres publicar. La IA usará estas notas para estructurar el guion del Reel. Puedes incluir detalles como:
- Nombre del evento o proyecto
- Fecha y hora
- Ubicación
- Descripción breve
- Enlaces relevantes
- Cualquier otro detalle que consideres importante para la publicación y el Reel.
EOT,
        ];
    }
}
