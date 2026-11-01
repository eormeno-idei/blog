<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\PostAgent;
use App\DTOs\ReelGenerationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StructureReelScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public int $postId,
        public string $notes,
        public ?string $previousJson = null,
        public ?string $userFeedback = null,
        public int $versionNumber = 1,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $isRefinement = $this->isRefinementMode();
        $this->reportGeneratingScriptStatus($isRefinement);

        try {
            $jsonString = $this->generateJsonScript($isRefinement);
            $decoded = $this->parseAndValidateScript($jsonString);
            [$sceneCount, $totalSeconds] = $this->calculateMetrics($decoded);

            $this->reportCompletedStatus($sceneCount, $totalSeconds, $jsonString, $decoded, $isRefinement);
        } catch (\Throwable $e) {
            Log::error('[StructureReelScriptJob] Error: ' . $e->getMessage());
            $this->reportFailedStatus($e->getMessage());
        }
    }

    private function isRefinementMode(): bool
    {
        return trim((string) $this->previousJson) !== ''
            && trim((string) $this->userFeedback) !== '';
    }

    private function reportGeneratingScriptStatus(bool $isRefinement): void
    {
        GenerateReelPreviewJob::putStatus($this->postId, ReelGenerationStatus::generatingScript(
            version: $this->versionNumber,
            isRefinement: $isRefinement,
            userFeedback: $this->userFeedback,
            message: $isRefinement
                ? 'Paso 1/1: Aplicando correcciones al guion con IA...'
                : 'Paso 1/1: Analizando notas y estructurando el guion con IA...',
            detail: $isRefinement
                ? "Ajuste solicitado: \"{$this->userFeedback}\""
                : 'Diseñando escenas, titulares y viñetas para el Reel.',
        ));
    }

    private function generateJsonScript(bool $isRefinement): string
    {
        $prompt = $this->buildPrompt($isRefinement);
        $answer = app(PostAgent::class)->prompt($prompt);

        return $this->sanitizeJsonResponse((string) $answer->text);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAndValidateScript(string $jsonString): array
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($jsonString, true);

        if (! is_array($decoded) || empty($decoded['scenes']) || ! is_array($decoded['scenes'])) {
            throw new \RuntimeException('La IA devolvió un guion con formato JSON inválido. Intenta nuevamente.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{0: int, 1: int}
     */
    private function calculateMetrics(array $decoded): array
    {
        /** @var list<array<string, mixed>> $scenes */
        $scenes = is_array($decoded['scenes'] ?? null) ? $decoded['scenes'] : [];
        $sceneCount = count($scenes);
        $totalSeconds = 0;

        foreach ($scenes as $scene) {
            $duration = $scene['duration_seconds'] ?? 0;
            $totalSeconds += is_numeric($duration) ? (int) $duration : 0;
        }

        $metadata = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
        $metaDuration = $metadata['duracion_total_segundos'] ?? 30;
        $fallbackSeconds = is_numeric($metaDuration) ? (int) $metaDuration : 30;

        return [$sceneCount, $totalSeconds > 0 ? $totalSeconds : $fallbackSeconds];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function reportCompletedStatus(
        int $sceneCount,
        int $totalSeconds,
        string $jsonString,
        array $decoded,
        bool $isRefinement
    ): void {
        GenerateReelPreviewJob::putStatus($this->postId, ReelGenerationStatus::completed(
            $this->versionNumber,
            $sceneCount,
            $totalSeconds,
            $jsonString,
            $decoded,
            '', // videoFilename is generated later in step 6
            $isRefinement ? $this->userFeedback : null,
        ));

        DB::table('posts')->where('id', $this->postId)->update([
            'ai_script' => $jsonString,
            'ai_job_status' => 'completed',
            'updated_at' => now(),
        ]);
    }

    private function reportFailedStatus(string $errorDetail): void
    {
        GenerateReelPreviewJob::putStatus($this->postId, ReelGenerationStatus::failed($this->versionNumber, $errorDetail));

        DB::table('posts')->where('id', $this->postId)->update([
            'ai_job_status' => 'failed',
            'updated_at' => now(),
        ]);
    }

    private function buildPrompt(bool $isRefinement): string
    {
        if (! $isRefinement) {
            return $this->notes;
        }

        return <<<PROMPT
        NOTAS ORIGINALES DEL PROYECTO:
        {$this->notes}

        GUION JSON ACTUAL:
        {$this->previousJson}

        SOLICITUD DE MODIFICACIÓN DEL USUARIO:
        {$this->userFeedback}

        Instrucción: Modifica el GUION JSON ACTUAL aplicando exactamente la SOLICITUD DE MODIFICACIÓN DEL USUARIO. Mantén el esquema JSON estricto y devuelve únicamente el JSON crudo resultante.
        PROMPT;
    }

    private function sanitizeJsonResponse(string $raw): string
    {
        $trimmed = trim($raw);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $trimmed = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $trimmed;
    }
}

