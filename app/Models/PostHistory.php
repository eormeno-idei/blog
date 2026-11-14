<?php

namespace App\Models;

use App\Enums\PostHistoryEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostHistory extends Model
{
    protected $table = 'post_history';

    protected $fillable = [
        'post_id',
        'actor_id',
        'event_type',
        'version',
        'based_on_history_id',
        'related_version_id',
        'summary',
        'instruction',
        'review_comment',
        'content_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => PostHistoryEventType::class,
            'content_snapshot' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * Post al que pertenece el evento.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Usuario que realizó la acción.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Versión utilizada como base para generar esta versión.
     */
    public function basedOnVersion(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'based_on_history_id'
        );
    }

    /**
     * Versiones generadas a partir de esta versión.
     */
    public function generatedVersions(): HasMany
    {
        return $this->hasMany(
            self::class,
            'based_on_history_id'
        );
    }

    /**
     * Versión a la que hace referencia este evento.
     */
    public function relatedVersion(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'related_version_id'
        );
    }

    /**
     * Eventos asociados a esta versión.
     */
    public function relatedEvents(): HasMany
    {
        return $this->hasMany(
            self::class,
            'related_version_id'
        );
    }
}
