<?php

// @usim: feature="admin", type="model"

namespace App\Models;

use Idei\Usim\Contracts\PairableActorInterface;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Traits\HasRoles;

// Lo dejamos preparado para el Token definitivo

/**
 * @property int $id
 * @property string $name
 * @property string|null $pairing_pin
 * @property string|null $device_token
 * @property array<string, mixed>|null $specs
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read Collection<int, UsimRole> $roles
 * @property-read Collection<int, UsimRole> $globalRoles
 * @property-read Collection<int, UsimUnit> $usimUnits
 */
class Device extends Authenticatable implements PairableActorInterface
{
    use HasApiTokens, HasRoles;

    /**
     * Guard de autenticación de Spatie y Laravel para dispositivos.
     */
    protected string $guard_name = 'device';

    protected $fillable = [
        'name',
        'pairing_pin',
        'device_token',
        'specs',
    ];

    protected $hidden = [
        'pairing_pin',
        'device_token',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
        ];
    }

    /**
     * Units that the device belongs to.
     *
     * @return MorphToMany<UsimUnit, $this>
     */
    public function usimUnits(): MorphToMany
    {
        return $this->morphToMany(UsimUnit::class, 'actor', 'usim_unit_actors')->withTimestamps();
    }

    /**
     * Roles across all units, bypassing Spatie's team filter.
     *
     * @return MorphToMany<UsimRole, $this>
     */
    public function globalRoles(): MorphToMany
    {
        /** @var class-string<UsimRole> $roleModel */
        $roleModel = config('permission.models.role', UsimRole::class);
        /** @var string $modelHasRolesTable */
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        /** @var string $modelMorphKey */
        $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

        return $this->morphToMany($roleModel, 'model', $modelHasRolesTable, $modelMorphKey, 'role_id');
    }

    /**
     * Determine if this device is public / institutional (assigned to 'main' or unassigned).
     */
    public function isPublic(): bool
    {
        $units = $this->relationLoaded('usimUnits') ? $this->usimUnits : $this->usimUnits()->get();

        return $units->isEmpty() || $units->contains('slug', 'main');
    }

    /**
     * Determine if this device is shared across multiple operational units.
     */
    public function isShared(): bool
    {
        $units = $this->relationLoaded('usimUnits') ? $this->usimUnits : $this->usimUnits()->get();

        return $units->filter(static fn (UsimUnit $u): bool => $u->type !== 'system' && ! in_array($u->slug, ['main', 'lobby'], true))->count() > 1;
    }

    /**
     * Determine if this device is currently paired with active credentials.
     */
    public function isPaired(): bool
    {
        if (! empty($this->device_token)) {
            return true;
        }

        return $this->relationLoaded('tokens')
            ? $this->tokens->isNotEmpty()
            : $this->tokens()->exists();
    }
}
