<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostTemplate;
use Idei\Usim\Enums\AspectRatio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property PostTemplate $template
 * @property AspectRatio $aspect_ratio
 * @property PostStatus $status
 * @property Carbon|null $from
 * @property Carbon|null $to
 * @property string|null $image
 * @property string|null $ai_script
 * @property string|null $reel_video
 * @property string $ai_job_status
 * @property string|null $ai_job_payload
 * @property string|null $reviewer_comment
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $author
 * @property-read User|null $reviewer
 */
class Post extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'template',
        'aspect_ratio',
        'status',
        'from',
        'to',
        'image',
        'ai_script',
        'reel_video',
        'ai_job_status',
        'ai_job_payload',
        'reviewer_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'template' => PostTemplate::DEFAULT,
        'aspect_ratio' => AspectRatio::_16_9,
        'status' => PostStatus::DRAFT,
        'ai_job_status' => 'idle',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'template' => PostTemplate::class,
            'aspect_ratio' => AspectRatio::class,
            'status' => PostStatus::class,
            'from' => 'datetime',
            'to' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that authored the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias for user relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the reviewer of the post.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
