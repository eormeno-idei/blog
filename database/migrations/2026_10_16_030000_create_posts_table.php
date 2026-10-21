<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Idei\Usim\Enums\AspectRatio;

return new class extends Migration
{
    public function up(): void
    {
        $aspects = AspectRatio::all();
        $defaultAspect = AspectRatio::default();

        Schema::create('posts', function (Blueprint $table,) use ($aspects, $defaultAspect) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['text', 'image'])->default('text');
            $table->string('background_color')->nullable();
            $table->string('background_image')->nullable();
            $table->string('text_color')->nullable();
            $table->enum('aspect_ratio', $aspects)->default($defaultAspect);
            $table->enum('font_style', ['normal', 'italic', 'bold'])->default('normal');
            $table->enum('font', ['sans-serif', 'serif', 'monospace'])->default('sans-serif');
            $table->dateTime('from')->nullable();
            $table->dateTime('to')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

