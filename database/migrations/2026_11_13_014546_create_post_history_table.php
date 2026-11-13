<?php

use App\Enums\PostHistoryEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $eventTypes = PostHistoryEventType::all();
        $defaultEvent = PostHistoryEventType::default();

        Schema::create('post_history', function (Blueprint $table) use ($eventTypes, $defaultEvent) {
            $table->id();

            // Post al que pertenece el evento.
            $table->foreignId('post_id')
                ->constrained()
                ->cascadeOnDelete();

            // Usuario que realizó la acción.
            // NULL cuando la acción es ejecutada automáticamente por el sistema.
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Tipo de evento.
            $table->enum('event_type', $eventTypes)->default($defaultEvent);

            // Numeración de versiones.
            // Solo se completan al generar una nueva versión.
            // Ejemplo: 1.0, 1.1, 1.2.
            $table->unsignedSmallInteger('version_major')->nullable();
            $table->unsignedSmallInteger('version_minor')->nullable();

            // Versión anterior utilizada como base para generar esta versión.
            $table->foreignId('based_on_history_id')
                ->nullable()
                ->constrained('post_history')
                ->nullOnDelete();

            // Versión sobre la que se realiza una acción.
            // Por ejemplo: enviar a revisión, aprobar o publicar.
            $table->foreignId('related_version_id')
                ->nullable()
                ->constrained('post_history')
                ->nullOnDelete();

            // Resumen breve del evento o modificación.
            $table->string('summary', 255)->nullable();

            // Instrucción enviada por el usuario a la IA.
            $table->longText('instruction')->nullable();

            // Observación o solicitud realizada por el revisor.
            $table->text('review_comment')->nullable();

            // Estructura completa del Reel en esa versión.
            // Se conserva como snapshot inmutable.
            $table->json('content_snapshot')->nullable();

            // Información complementaria:
            // plantilla aplicada, redes sociales, datos de publicación, etc.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Evita versiones duplicadas dentro de un mismo post.
            $table->unique(
                ['post_id', 'version_major', 'version_minor'],
                'post_history_version_unique'
            );

            // Consultas cronológicas del historial.
            $table->index(['post_id', 'created_at']);

            // Consultas por tipo de evento.
            $table->index(['post_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_history');
    }
};
