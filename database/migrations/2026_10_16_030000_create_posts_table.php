<?php

use Idei\Usim\Enums\AspectRatio;
use App\Enums\PostStatus;
use App\Enums\PostTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $aspects = AspectRatio::all();
        $defaultAspect = AspectRatio::default();

        $statuses = PostStatus::all();
        $defaultStatus = PostStatus::default();

        $templates = PostTemplate::all();
        $defaultTemplate = PostTemplate::default();

        Schema::create('posts', function (Blueprint $table) use (
            $aspects,   $defaultAspect,
            $statuses,  $defaultStatus,
            $templates, $defaultTemplate
        ) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('template', $templates)->default($defaultTemplate);
            $table->enum('aspect_ratio', $aspects)->default($defaultAspect);
            $table->enum('status', $statuses)->default($defaultStatus);
            $table->dateTime('from')->nullable();
            $table->dateTime('to')->nullable();
            $table->string('image')->nullable();
            $table->text('ai_script')->nullable();
            $table->string('reel_video')->nullable();
            $table->string('ai_job_status')->default('idle');
            $table->longText('ai_job_payload')->nullable();
            $table->text('reviewer_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
