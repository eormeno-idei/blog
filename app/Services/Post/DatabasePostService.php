<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePostService implements PostServiceInterface
{
    public function getLatestPosts(int $ownerId = 0, string $filter = ''): Collection
    {
        $this->archiveExpiredPosts();

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
            'title' => $data['title'],
            'content' => $data['content'],
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
            'reviewer_comment' => $data['reviewer_comment'] ?? null,
            'reviewed_by' => $data['reviewed_by'] ?? null,
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'reviewer_comment',
            'reviewed_by',
            'reviewed_at',
        ];

        $payload = array_intersect_key($data, array_flip($allowedKeys));
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
        return DB::table('posts')->where('id', $id)->delete() > 0;
    }
}
