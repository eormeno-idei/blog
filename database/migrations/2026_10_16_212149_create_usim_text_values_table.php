<?php
// @usim: feature="admin", type="migration"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usim_text_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('text_key_id')->constrained('usim_text_keys')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('usim_languages')->cascadeOnDelete();
            $table->text('text_value')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('media_url')->nullable();
            $table->json('media_meta')->nullable();
            $table->timestamps();

            $table->unique(['text_key_id', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usim_text_values');
    }
};
