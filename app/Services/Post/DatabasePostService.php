<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePostService implements PostServiceInterface
{
    public function getLatestPosts(int $ownerId = 0, string $filter = ''): Collection
    {
        $query = DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select(
                'posts.*',
                'users.id as author_id',
                'users.name as author_name'
            );
        
        if ($ownerId > 0) {
            $query->where('posts.user_id', $ownerId);
        }

        if (!empty($filter)) {
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
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select(
                'posts.*',
                'users.id as author_id',
                'users.name as author_name'
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
            'background_color' => $data['background_color'] ?? null,
            'text_color' => $data['text_color'] ?? null,
            'background_image' => $data['background_image'] ?? null,
            'aspect_ratio' => $data['aspect_ratio'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'status' => $data['status'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function deletePost(int $id): bool
    {
        return DB::table('posts')->where('id', $id)->delete() > 0;
    }
}
