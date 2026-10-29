<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostTemplate;
use Idei\Usim\Enums\AspectRatio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonImmutable;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property PostTemplate $template
 * @property AspectRatio $aspect_ratio
 * @property PostStatus $status
 * @property CarbonImmutable|null $from
 * @property CarbonImmutable|null $to
 * @property string|null $image
 * @property string|null $ai_script
 * @property string|null $reel_video
 * @property string $ai_job_status
 * @property string|null $ai_job_payload
 * @property string|null $reviewer_comment
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
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
        'template' => PostTemplate::DEFAULT->value,
        'aspect_ratio' => AspectRatio::_16_9->value,
        'status' => PostStatus::DRAFT->value,
        'ai_job_status' => 'idle',
        'ai_job_payload' => null,
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
            'from' => 'immutable_datetime',
            'to' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'ai_job_payload' => 'array',
        ];
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
