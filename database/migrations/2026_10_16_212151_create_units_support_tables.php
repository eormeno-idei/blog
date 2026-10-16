<?php
// @usim: feature="admin", type="migration"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usim_units', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type')->nullable();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('usim_units')
                ->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create('usim_unit_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usim_unit_id')->constrained('usim_units')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['usim_unit_id', 'user_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Se elimina primero la tabla pivote para liberar las dependencias
        Schema::dropIfExists('usim_unit_user');
        Schema::dropIfExists('usim_units');
    }
};
