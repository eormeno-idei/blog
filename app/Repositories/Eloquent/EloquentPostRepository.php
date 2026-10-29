<?php

namespace App\Repositories\Eloquent;

use App\Models\Post;
use App\Models\User;
use App\DTOs\Posts\PostDTO;
use App\DTOs\User\UserDTO;
use App\Repositories\Contracts\PostRepositoryInterface;
use Idei\Usim\Enums\AspectRatio;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function findById(int $id, array $with = []): ?PostDTO
    {
        $post = Post::with($with)->find($id);

        if (!$post) {
            return null;
        }

        return $this->toDTO($post);
    }

    /**
     * Transformador Principal
     */
    private function toDTO(Post $post): PostDTO
    {
        return new PostDTO(
            id: $post->id,
            author: $post->user_id,
            title: $post->title,
            content: $post->content,
            status: $post->status,
            aspectRatio: AspectRatio::tryFrom($post->aspect_ratio),

            // LA MAGIA: Solo mapeamos el UserDTO si la relación 'user' fue cargada (Eager Load)
            author: $post->relationLoaded('user') && $post->user
            ? $this->toUserDTO($post->user)
            : null,
        );
    }

    /**
     * Transformadores Secundarios
     */
    private function toUserDTO(User $user): UserDTO
    {
        return new UserDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
