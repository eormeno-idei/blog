<?php

namespace App\Jobs;

use App\Ai\Agents\PostAgent;
use App\DTOs\ReelStructureGenerationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Contracts\PostReelGenerationStatusInterface;

class GenerateReelStructureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public int $postId,
        public string $notes,
        public ?string $initialInstructions,
    ) {
        $this->onQueue('videos');
    }

    public static function getStatus(int $postId): ?ReelStructureGenerationStatus
    {
        $postReelGenerationStatusService = app()->make(PostReelGenerationStatusInterface::class);
        return $postReelGenerationStatusService->getStatus($postId);
    }

    /**
     * Persiste el estado del procesamiento en la tabla `posts`.
     */
    public static function putStatus(ReelStructureGenerationStatus $payload): void
    {
        $postReelGenerationStatusService = app()->make(PostReelGenerationStatusInterface::class);
        $postReelGenerationStatusService->putStatus($payload);
    }

    public function handle(): void
    {
        $jsonPath = null;

        try {
            $jsonString = $this->generateJsonScript();
            $decoded = $this->parseAndValidateScript($jsonString);
            [$sceneCount, $totalSeconds] = $this->calculateMetrics($decoded);

            $this->reportCompletedStatus($this->postId,$sceneCount, $totalSeconds, $jsonString, $decoded);
        } catch (\Throwable $e) {
            Log::error('[GenerateReelPreviewJob] Error: ' . $e->getMessage());
        } finally {
            $this->deleteTempJsonFile($jsonPath);
        }
    }

    private function generateJsonScript(): string
    {
        $prompt = $this->buildPrompt();
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

        if (!is_array($decoded) || empty($decoded['scenes']) || !is_array($decoded['scenes'])) {
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
        $scenes = $decoded['scenes'];
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
        int $postId,
        int $sceneCount,
        int $totalSeconds,
        string $jsonString,
        array $decoded,
    ): void {
        self::putStatus(ReelStructureGenerationStatus::completed(
            $postId,
            $sceneCount,
            $totalSeconds,
            $jsonString,
            $decoded,
        ));
    }

    private function deleteTempJsonFile(?string $jsonPath): void
    {
        if ($jsonPath !== null && File::exists($jsonPath)) {
            File::delete($jsonPath);
        }
    }

    private function buildPrompt(): string
    {
        if (!$this->initialInstructions) {
            return $this->notes;
        }

        return <<<PROMPT
        INFORMACIÓN GENERAL:
        {$this->notes}

        INSTRUCCIONES INICIALES DEL USUARIO:
        {$this->initialInstructions}

        Instrucción: Ten en cuenta las instrucciones iniciales del usuario considerando ESTRICTAMENTE DICHA SOLICITUD. Mantén el esquema JSON estricto y devuelve únicamente el JSON crudo resultante.
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
