<?php

namespace App\Repositories\Eloquent;

use App\Models\Post;
use App\DTOs\Posts\PostDTO;
use App\Enums\PostStatus;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Eloquent\Concerns\HasUserDTO;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonImmutable;

class PostRepository implements PostRepositoryInterface
{
    use HasUserDTO;

    /** @var array<int, string> */
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PostDTO
    {
        $post = Post::create($data);
        $post->load(self::$defaultWith);
        return $this->toDTO($post);
    }

    /**
     * @param  array<string, mixed>  $data
     */
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
            workflowStep: $post->workflow_step,
            title: $post->title,
            content: $post->content,
            template: $post->template,
            status: $post->status->value,
            aspectRatio: $post->aspect_ratio,
            from: $post->from ? CarbonImmutable::parse($post->from) : null,
            to: $post->to ? CarbonImmutable::parse($post->to) : null,
            image: $post->image,
            aiScript: $post->ai_script,
            reelVideo: $post->reel_video,
            aiJobStatus: $post->ai_job_status,
            aiJobPayload: is_array($post->ai_job_payload) ? (json_encode($post->ai_job_payload) ?: null) : (is_string($post->ai_job_payload) ? $post->ai_job_payload : null),
            reviewerComment: $post->reviewer_comment,
            reviewedAt: $post->reviewed_at ? CarbonImmutable::parse($post->reviewed_at) : null,
            createdAt: $post->created_at ? CarbonImmutable::parse($post->created_at) : null,
            updatedAt: $post->updated_at ? CarbonImmutable::parse($post->updated_at) : null,
            author: $this->toUserDTO($post->author),
            reviewedBy: $this->toUserDTO($post->reviewer),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PostDTO>
     */
    public function get(array $filters = []): Collection
    {
        $query = $this->buildFilterQuery($filters);
        /** @var \Illuminate\Database\Eloquent\Collection<int, Post> $posts */
        $posts = $query->get();

        return $posts->map(fn(Post $post) => $this->toDTO($post));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PostDTO>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildFilterQuery($filters);
        $paginator = $query->paginate($perPage);
        $posts = $paginator->getCollection()->map(fn(Post $post) => $this->toDTO($post));
        $paginator->setCollection($posts);

        /** @var LengthAwarePaginator<int, PostDTO> $paginator */
        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Post>
     */
    private function buildFilterQuery(array $filters): Builder
    {
        $query = Post::with(self::$defaultWith);

        // Permite ver posts de todos (no draft) + borradores propios
        if (isset($filters['own_drafts_only_user_id'])) {
            $userId = $filters['own_drafts_only_user_id'];
            $query->where(function (Builder $q) use ($userId) {
                $q->where('status', '!=', PostStatus::DRAFT->value)
                    ->orWhere('user_id', $userId);
            });
        }

        if (isset($filters['exclude_status'])) {
            $statuses = (array) $filters['exclude_status'];
            $query->whereNotIn('status', $statuses);
        }
        if (isset($filters['exclude_ids'])) {
            $query->whereNotIn('id', (array) $filters['exclude_ids']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['search']) && is_string($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function getTitlesByPrefix(int $userId, string $prefix): array
    {
        /** @var array<int, string> $titles */
        $titles = Post::where('user_id', $userId)
            ->where('title', 'like', "{$prefix}%")
            ->pluck('title')
            ->toArray();

        return $titles;
    }
}
