<?php

namespace App\Repositories\Eloquent;

use App\Models\Post;
use App\Models\User;
use App\DTOs\Posts\PostDTO;
use App\DTOs\User\UserDTO;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonImmutable;

class EloquentPostRepository implements PostRepositoryInterface
{
    private static array $defaultWith = ['author', 'reviewer'];

    public function findById(int $id): ?PostDTO
    {
        $post = Post::with(self::$defaultWith)->find($id);

        if (!$post) {
            return null;
        }

        return $this->toDTO($post);
    }

    public function delete(int $id): bool
    {
        return (bool) Post::where('id', $id)->delete();
    }

    public function create(array $data): PostDTO
    {
        $post = Post::create($data);
        $post->load(self::$defaultWith);
        return $this->toDTO($post);
    }

    public function update(int $id, array $data): ?PostDTO
    {
        $post = Post::find($id);

        if (!$post) {
            return null;
        }

        $post->update($data);
        $post->load(self::$defaultWith);
        return $this->toDTO($post);
    }

    private function toDTO(Post $post): PostDTO
    {
        return new PostDTO(
            id: $post->id,
            title: $post->title,
            content: $post->content,
            template: $post->template,
            status: $post->status,
            aspectRatio: $post->aspect_ratio,
            from: $post->from ? CarbonImmutable::parse($post->from) : null,
            to: $post->to ? CarbonImmutable::parse($post->to) : null,
            image: $post->image,
            aiScript: $post->ai_script,
            reelVideo: $post->reel_video,
            aiJobStatus: $post->ai_job_status,
            aiJobPayload: $post->ai_job_payload,
            reviewerComment: $post->reviewer_comment,
            reviewedAt: $post->reviewed_at ? CarbonImmutable::parse($post->reviewed_at) : null,
            createdAt: $post->created_at ? CarbonImmutable::parse($post->created_at) : null,
            updatedAt: $post->updated_at ? CarbonImmutable::parse($post->updated_at) : null,
            author: $this->toUserDTO($post->author),
            reviewedBy: $this->toUserDTO($post->reviewer),
        );
    }

    public function get(array $filters = []): Collection
    {
        $query = $this->buildFilterQuery($filters);
        return $query->get()->map(fn(Post $post) => $this->toDTO($post));
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildFilterQuery($filters);
        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(fn(Post $post) => $this->toDTO($post));

        return $paginator;
    }

    private function buildFilterQuery(array $filters): Builder
    {
        $query = Post::with(self::$defaultWith);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        return $query;
    }

    private function toUserDTO(?User $user): ?UserDTO
    {
        if (!$user) {
            return null;
        }

        return new UserDTO(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at,
            termsAcceptedAt: $user->terms_accepted_at,
            isRoot: $user->is_root,
            isRegisteredInLobby: $user->is_registered_in_lobby,
            displayName: $user->display_name,
        );
    }
}
