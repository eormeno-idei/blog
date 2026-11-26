<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use App\DTOs\Posts\PostDTO;
use App\Models\User;
use App\Services\User\UserService;
use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\Enums\Style;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PostCardGridService implements CardListable
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private const COVER_PALETTES = [
        ['#1e293b', '#312e81', '#818cf8'],
        ['#172554', '#1e3a5f', '#38bdf8'],
        ['#1e1b4b', '#3b0764', '#a78bfa'],
        ['#0f172a', '#134e4a', '#2dd4bf'],
        ['#1c1917', '#422006', '#fbbf24'],
    ];

    public function __construct(
        private readonly PostServiceInterface $postService,
        private readonly UserService $userService,
    ) {}

    public function query(CardQuery $query): CardPageResult
    {
        $user = Auth::user();
        $posts = $this->postService->getLatestPosts($user);

        $filtered = $this->applyFilters($posts, $query, $user);
        $sorted = $this->applySort($filtered, $query);

        $cards = $sorted->map(
            fn (PostDTO $post): CardItemDTO => $this->toCardItemDTO($post, $user)
        );

        return CardPageResult::fromCollection($cards, $query);
    }

    public function toCardItemDTO(PostDTO $post, ?Authenticatable $user = null): CardItemDTO
    {
        $user ??= Auth::user();

        $isOwner = $this->isOwner($post, $user);
        $isReviewer = $this->isReviewer($user);
        $status = $post->statusValue();

        [$badgeText, $badgeStyle] = $this->resolveStatusBadge($post);
        [$imageSource, $imageFit] = $this->resolveImage($post);

        $authorName = $isOwner ? 'Tú' : ($post->author->name ?? '');
        $subtitle = $authorName !== '' ? "Autor: {$authorName}" : null;
        $updatedLabel = $post->updatedAt?->diffForHumans() ?? $post->createdAt?->diffForHumans();

        $description = $this->resolveDescription($post, $isOwner, $isReviewer);
        $actions = $this->buildActions($post, $user, $isOwner, $isReviewer);
        [$clickAction, $clickParameters] = $this->resolveClickAction($post, $user, $isOwner, $isReviewer);

        return CardItemDTO::make(
            id: $post->id,
            title: $post->title,
            subtitle: $subtitle,
            eyebrow: $this->resolveEyebrow($post),
            description: $description,
            image: $imageSource,
            imageAlt: $post->title,
            imageFit: $imageFit,
            aspectRatio: '16/9',
            badge: $badgeText,
            badgeStyle: $badgeStyle,
            footerText: $updatedLabel,
            actions: $actions,
            clickAction: $clickAction,
            clickParameters: $clickParameters,
            style: $this->resolveCardStyle($post),
            meta: [
                'status' => $status,
                'ai_job_status' => $post->aiJobStatus,
                'is_owner' => $isOwner,
            ],
        );
    }

    /**
     * @param  Collection<int, PostDTO>  $posts
     * @return Collection<int, PostDTO>
     */
    private function applyFilters(Collection $posts, CardQuery $query, ?Authenticatable $user): Collection
    {
        $statusFilter = $query->filter('status');
        if (is_string($statusFilter) && trim($statusFilter) !== '') {
            $wantedStatus = trim($statusFilter);
            $posts = $posts->filter(
                static fn (PostDTO $post): bool => $post->statusValue() === $wantedStatus
            );
        }

        $term = mb_strtolower(trim($query->search));
        if ($term !== '') {
            $isReviewer = $this->isReviewer($user);
            $posts = $posts->filter(function (PostDTO $post) use ($term, $user, $isReviewer): bool {
                $parts = [
                    $post->title,
                    $post->author->name ?? '',
                    strip_tags($post->content),
                ];

                if ($post->reviewerComment !== null && ($isReviewer || $this->isOwner($post, $user))) {
                    $parts[] = $post->reviewerComment;
                }

                return str_contains(mb_strtolower(implode(' ', $parts)), $term);
            });
        }

        return $posts->values();
    }

    /**
     * @param  Collection<int, PostDTO>  $posts
     * @return Collection<int, PostDTO>
     */
    private function applySort(Collection $posts, CardQuery $query): Collection
    {
        $sortBy = $query->sortBy ?? 'updated_at';
        $descending = $query->sortDirection === 'desc';

        $callback = match ($sortBy) {
            'title' => static fn (PostDTO $post): string => mb_strtolower($post->title),
            'status' => static fn (PostDTO $post): string => $post->statusValue(),
            default => static fn (PostDTO $post): int => ($post->updatedAt ?? $post->createdAt)?->getTimestamp() ?? 0,
        };

        $sorted = $descending
            ? $posts->sortByDesc($callback)
            : $posts->sortBy($callback);

        return $sorted->values();
    }

    /**
     * @return array{0: string, 1: Style}
     */
    private function resolveStatusBadge(PostDTO $post): array
    {
        $status = $post->statusValue();
        $jobStatus = $post->aiJobStatus;

        if ($status === 'draft' && in_array($jobStatus, ['generating_script', 'rendering_video'], true)) {
            return ['⚙️ Procesando IA', Style::INFO];
        }

        return match ($status) {
            'pending_review' => ['⏳ En revisión', Style::INFO],
            'changes_requested' => ['⚠️ Con observaciones', Style::DANGER],
            'published' => ['🟢 Publicado', Style::SUCCESS],
            'archived' => ['📦 Archivado', Style::MUTED],
            default => ['📝 Borrador', Style::WARNING],
        };
    }

    private function resolveCardStyle(PostDTO $post): Style
    {
        $status = $post->statusValue();
        $jobStatus = $post->aiJobStatus;

        if ($status === 'draft' && in_array($jobStatus, ['generating_script', 'rendering_video'], true)) {
            return Style::INFO;
        }

        return match ($status) {
            'pending_review' => Style::INFO,
            'changes_requested' => Style::DANGER,
            'published' => Style::SUCCESS,
            'archived' => Style::MUTED,
            default => Style::WARNING,
        };
    }

    private function resolveEyebrow(PostDTO $post): string
    {
        $parts = ['Publicación'];

        if (is_string($post->reelVideo) && trim($post->reelVideo) !== '') {
            $parts[] = 'Reel';
        }

        if ($post->from !== null) {
            $parts[] = $post->from->format('d/m/Y');
        }

        return implode(' • ', $parts);
    }

    /**
     * @return array{0: string, 1: 'cover'|'contain'|'scale-down'}
     */
    private function resolveImage(PostDTO $post): array
    {
        $rawImage = is_string($post->image) ? trim($post->image) : '';

        if ($rawImage !== '') {
            // CardGrid::imageStorage('uploads/images') resuelve rutas relativas automáticamente
            return [$rawImage, 'scale-down'];
        }

        return [$this->buildEditorialCoverSvg($post), 'cover'];
    }

    private function buildEditorialCoverSvg(PostDTO $post): string
    {
        $palette = self::COVER_PALETTES[abs($post->id) % count(self::COVER_PALETTES)];
        [$bgStart, $bgEnd, $accent] = $palette;

        $initials = $this->extractMonogram($post->title);
        $escapedInitials = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$bgStart}"/>
      <stop offset="100%" stop-color="{$bgEnd}"/>
    </linearGradient>
    <radialGradient id="glow" cx="80%" cy="20%" r="60%">
      <stop offset="0%" stop-color="{$accent}" stop-opacity="0.28"/>
      <stop offset="100%" stop-color="{$accent}" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="640" height="360" fill="url(#g)"/>
  <rect width="640" height="360" fill="url(#glow)"/>
  <g stroke="{$accent}" stroke-opacity="0.12" stroke-width="1">
    <line x1="0" y1="90" x2="640" y2="90"/>
    <line x1="0" y1="180" x2="640" y2="180"/>
    <line x1="0" y1="270" x2="640" y2="270"/>
    <line x1="160" y1="0" x2="160" y2="360"/>
    <line x1="320" y1="0" x2="320" y2="360"/>
    <line x1="480" y1="0" x2="480" y2="360"/>
  </g>
  <circle cx="320" cy="180" r="56" fill="{$accent}" fill-opacity="0.14" stroke="{$accent}" stroke-opacity="0.4" stroke-width="1.5"/>
  <text x="320" y="182" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif" font-size="34" font-weight="700" letter-spacing="2" fill="#f8fafc">{$escapedInitials}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function extractMonogram(string $title): string
    {
        $words = preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($words) || $words === []) {
            return 'P';
        }

        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            if (is_string($clean) && $clean !== '') {
                $letters .= mb_strtoupper(mb_substr($clean, 0, 1));
            }
        }

        return $letters !== '' ? $letters : 'P';
    }

    private function resolveDescription(PostDTO $post, bool $isOwner, bool $isReviewer): ?string
    {
        $reviewerComment = $post->reviewerComment !== null ? trim($post->reviewerComment) : '';
        if ($reviewerComment !== '' && ($isOwner || $isReviewer)) {
            return '💬 Obs: '.$reviewerComment;
        }

        $plainContent = trim(preg_replace('/\s+/', ' ', strip_tags($post->content)) ?? '');

        return $plainContent !== '' ? $plainContent : null;
    }

    /**
     * @return list<CardActionDTO>
     */
    private function buildActions(PostDTO $post, ?Authenticatable $user, bool $isOwner, bool $isReviewer): array
    {
        $actions = [];
        $status = $post->statusValue();

        if ($isReviewer && $status === 'pending_review' && ! $isOwner) {
            $actions[] = CardActionDTO::make(
                label: 'Revisar',
                action: 'review_post',
                parameters: ['post_id' => $post->id],
                icon: 'eye',
                style: Style::INFO,
                variant: 'solid',
            );
        }

        if (($user instanceof User) && $user->can('update', $post)) {
            $isCorrection = $status === 'changes_requested';
            $actions[] = CardActionDTO::make(
                label: $isCorrection ? 'Corregir' : 'Editar',
                action: 'edit_post',
                parameters: ['post_id' => $post->id],
                icon: 'edit',
                style: $isCorrection ? Style::WARNING : Style::SECONDARY,
                variant: $isCorrection ? 'solid' : 'outline',
            );
        }

        if (($user instanceof User) && $user->can('delete', $post)) {
            $actions[] = CardActionDTO::make(
                label: '',
                action: 'delete_post',
                parameters: ['post_id' => $post->id],
                icon: 'trash',
                style: Style::DANGER,
                variant: 'outline',
                tooltip: 'Eliminar post',
            );
        }

        return $actions;
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function resolveClickAction(PostDTO $post, ?Authenticatable $user, bool $isOwner, bool $isReviewer): array
    {
        $status = $post->statusValue();

        if ($isReviewer && $status === 'pending_review' && ! $isOwner) {
            return ['review_post', ['post_id' => $post->id]];
        }

        if (($user instanceof User) && $user->can('update', $post)) {
            return ['edit_post', ['post_id' => $post->id]];
        }

        return [null, []];
    }

    private function isOwner(PostDTO $post, ?Authenticatable $user): bool
    {
        if ($user === null || $post->author === null) {
            return false;
        }

        return $user->getAuthIdentifier() === $post->author->id;
    }

    private function isReviewer(?Authenticatable $user): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ($user->isRoot() || $user->can('post.review') || $user->hasRole(['reviewer', 'admin'])) {
            return true;
        }

        return Auth::user() === $user && $this->userService->currentUserHasRole(['reviewer', 'admin']);
    }
}
