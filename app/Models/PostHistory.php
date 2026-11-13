<?php

namespace App\Models;

use App\Enums\PostHistoryEventType;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'version_major',
        'version_minor',
        'based_on_history_id',
        'related_version_id',
        'summary',
        'instruction',
        'review_comment',
        'content_snapshot',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => PostHistoryEventType::class,
            'content_snapshot' => 'array',
            'metadata' => 'array',
            'version_major' => 'integer',
            'version_minor' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Etiqueta de versión. Ejemplo: 1.2
     */
    protected function versionLabel(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (
                $this->version_major == null ||
                $this->version_minor == null
            ) {
                return null;
            }

            return "{$this->version_major}.{$this->version_minor}";
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filtra exclusivamente las versiones generadas.
     */
    public function scopeVersions($query)
    {
        return $query->where(
            'event_type',
            PostHistoryEventType::VERSION_GENERATED->value
        );
    }
}
