<?php
// @usim: feature="admin", type="migration"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usim_languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_fallback')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usim_languages');
    }
};
