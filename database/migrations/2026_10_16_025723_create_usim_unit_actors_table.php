<?php
// @usim: feature="admin", type="migration"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usim_unit_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usim_unit_id')->constrained('usim_units')->cascadeOnDelete();

            // Crea actor_type (VARCHAR) y actor_id (BIGINT) indexados
            $table->morphs('actor');

            $table->timestamps();

            // Garantiza que un mismo actor no pueda ser agregado dos veces a la misma unidad
            $table->unique(['usim_unit_id', 'actor_type', 'actor_id'], 'unit_actor_unique');
        });

        // Si tienes datos previos en desarrollo, puedes migrar de unit_user a unit_actors aquí.
        // Si no, simplemente eliminamos la tabla vieja:
        Schema::dropIfExists('usim_unit_user');
    }

    public function down(): void
    {
        Schema::dropIfExists('usim_unit_actors');

        // Recrear usim_unit_user si fuera necesario hacer rollback
        Schema::create('usim_unit_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usim_unit_id')->constrained('usim_units')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
