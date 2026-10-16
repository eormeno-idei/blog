<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePostService implements PostServiceInterface
{
    public function getLatestPosts(): Collection
    {
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.*', 'users.name as author_name')
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->get();
    }

    public function findById(int $id): ?object
    {
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.*', 'users.name as author_name')
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function deletePost(int $id): bool
    {
        return DB::table('posts')->where('id', $id)->delete() > 0;
    }
}

