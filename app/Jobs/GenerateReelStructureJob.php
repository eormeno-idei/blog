<?php

namespace App\Jobs;

use App\Ai\Agents\PostAgent;
use App\Contracts\PostHistoryServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\DTOs\Posts\PostHistoryEventDTO;
use App\Models\Post;

class GenerateReelStructureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    protected PostHistoryServiceInterface $historyService;
    protected PostHistoryEventDTO $generatingEvent;

    public function __construct(
        public PostHistoryEventDTO $initialEvent,
        public string $context
    ) {
        $this->onQueue('videos');
    }

    public function handle(PostHistoryServiceInterface $historyService): void
    {
        $this->historyService = $historyService;

        try {
            $this->generatingEvent = $this->historyService->createGeneratingReelScriptEvent($this->initialEvent);
            $rawReelScript = $this->generateScript();
            $reelScript = $this->parseAndValidateScript($rawReelScript);
            $this->historyService->createCompletedReelScriptEvent($this->generatingEvent, $reelScript);
        } catch (\Throwable $e) {
            $this->historyService->createFailedReelScriptEvent($this->generatingEvent, $e->getMessage());
            Log::error('[GenerateReelPreviewJob] Error: ' . $e->getMessage());
        }
    }

    private function generateScript(): string
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

        if (isset($decoded['errors']) && is_array($decoded['errors']) && !empty($decoded['errors'])) {
            $errorMessages = implode('; ', array_map('strval', $decoded['errors']));
            throw new \RuntimeException('La solicitud fue rechazada: ' . $errorMessages);
        }

        return $decoded;
    }

    // /**
    //  * @param  array<string, mixed>  $decoded
    //  * @return array{0: int, 1: int}
    //  */
    // private function calculateMetrics(array $decoded): array
    // {
    //     /** @var list<array<string, mixed>> $scenes */
    //     $scenes = $decoded['scenes'];
    //     $sceneCount = count($scenes);
    //     $totalSeconds = 0;

    //     foreach ($scenes as $scene) {
    //         $duration = $scene['duration_seconds'] ?? 0;
    //         $totalSeconds += is_numeric($duration) ? (int) $duration : 0;
    //     }

    //     $metadata = isset($decoded['metadata']) && is_array($decoded['metadata']) ? $decoded['metadata'] : [];
    //     $metaDuration = $metadata['duracion_total_segundos'] ?? 30;
    //     $fallbackSeconds = is_numeric($metaDuration) ? (int) $metaDuration : 30;

    //     return [$sceneCount, $totalSeconds > 0 ? $totalSeconds : $fallbackSeconds];
    // }

    private function buildPrompt(): string
    {
        if (!$this->initialEvent->extra_instructions) {
            return $this->context;
        }

        return <<<PROMPT
        INFORMACIÓN GENERAL:
        {$this->context}

        INSTRUCCIONES INICIALES DEL USUARIO:
        {$this->initialEvent->extra_instructions}

        Instrucción: Ten en cuenta las instrucciones iniciales del usuario considerando
        ESTRICTAMENTE DICHA SOLICITUD. Mantén el esquema JSON estricto y devuelve únicamente el JSON crudo resultante.
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
