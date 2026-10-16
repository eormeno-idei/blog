<?php
// @usim: feature="core", type="test"
namespace Tests\Support;

use App\Models\User;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

final class UiScenario
{
    private const STORAGE_HEADER = 'header';
    private const STORAGE_BODY = 'body';
    private const STORAGE_NONE = 'none';

    private TestCase $test;

    private UiMemoryRenderer $memory;

    private string $clientId;

    private string $screenClass;

    /** @var array<string, mixed> */
    private array $screenQuery;

    private static bool $persistentCacheConfigured = false;

    private ?TestResponse $lastResponse = null;

    private function __construct(TestCase $test)
    {
        $this->test = $test;
        $this->memory = new UiMemoryRenderer();
        $this->clientId = (string) Str::uuid();
        $this->screenClass = '';
        $this->screenQuery = [];
    }

    public static function boot(TestCase $test, string $screenClass, array $query = []): self
    {
        if (class_exists(\Idei\Usim\Layout\AbstractLayout::class)) {
            \Idei\Usim\Layout\AbstractLayout::setCurrent(null);
        }

        $scenario = new self($test);
        $scenario->configurePersistentCacheForUiState();
        $scenario->screenClass = $screenClass;
        $scenario->screenQuery = array_filter(
            $query,
            static fn ($key): bool => (string) $key !== 'reset',
            ARRAY_FILTER_USE_KEY
        );

        $existingActiveUnit = UIStateManager::getActiveUnit();
        if ($existingActiveUnit !== null) {
            UIStateManager::setActiveUnit($existingActiveUnit, $scenario->clientId);
        }

        $existingAuthToken = null;
        $actor = Auth::guard('web')->user() ?? Auth::guard('device')->user();
        if ($actor !== null && method_exists($actor, 'createToken')) {
            $existingAuthToken = $actor->createToken('scenario-' . $scenario->clientId)->plainTextToken;
        }

        if ($existingAuthToken !== null) {
            UIStateManager::setAuthToken($existingAuthToken, $scenario->clientId);
            $storageKey = (string) config('usim.front_store_key', 'usim-framework');
            $scenario->memory->ingest(['storage' => [$storageKey => json_encode(['store_token' => $existingAuthToken])]], 'auto');
        }

        session()->put(UIStateManager::CLIENT_ID_COOKIE, $scenario->clientId);
        UIStateManager::clearClientActiveModal($scenario->clientId);
        UIStateManager::clearClientCurrentScreen($scenario->clientId);

        $headers = [];
        $usimStorage = $scenario->memory->usimStorage();
        if ($usimStorage !== '') {
            $headers['X-USIM-Storage'] = $usimStorage;
        }

        $response = $scenario
            ->testWithClientCookie()
            ->getJson(screenApiUrl($screenClass, $query), $headers);
        $response->assertOk();

        $scenario->ingest($response->json(), 'initial');

        return $scenario;
    }

    public function ingest(array $payload, string $mode = 'auto'): self
    {
        $this->memory->ingest($payload, $mode);
        return $this;
    }

    public function opaqueUsim(): string
    {
        return $this->memory->opaqueUsim();
    }

    public function component(string $name): UiComponentRef
    {
        if ($this->memory->componentByName($name) === null) {
            throw new RuntimeException("Component not found by name: {$name}");
        }

        return new UiComponentRef($this, $name);
    }

    public function click(string $componentName, array $parameters = []): TestResponse
    {
        $component = $this->mustGetComponent($componentName);
        $action = $component['action'] ?? null;

        if (!is_string($action) || $action === '') {
            throw new RuntimeException("Component '{$componentName}' has no action configured.");
        }

        $componentId = $this->mustGetComponentId($componentName);

        return $this->sendUiEvent(
            componentId: $componentId,
            event: 'click',
            action: $action,
            parameters: $parameters,
            storageMode: self::STORAGE_HEADER,
            syncComponentId: $componentId
        );
    }

    public function input(string $componentName, string $action, array $parameters = []): TestResponse
    {
        $componentId = $this->mustGetComponentId($componentName);

        return $this->sendUiEvent(
            componentId: $componentId,
            event: 'input',
            action: $action,
            parameters: $parameters,
            storageMode: self::STORAGE_BODY,
            syncComponentId: $componentId
        );
    }

    public function change(
        string $componentName,
        string $action,
        array $parameters = [],
        bool $includeStorageHeader = true
    ): TestResponse {
        $componentId = $this->mustGetComponentId($componentName);

        return $this->sendUiEvent(
            componentId: $componentId,
            event: 'change',
            action: $action,
            parameters: $parameters,
            storageMode: $includeStorageHeader ? self::STORAGE_HEADER : self::STORAGE_NONE,
            syncComponentId: $componentId
        );
    }

    public function action(
        string $componentName,
        string $action,
        array $parameters = [],
        bool $includeStorageHeader = true
    ): TestResponse {
        $componentId = $this->mustGetComponentId($componentName);

        return $this->sendUiEvent(
            componentId: $componentId,
            event: 'action',
            action: $action,
            parameters: $parameters,
            storageMode: $includeStorageHeader ? self::STORAGE_HEADER : self::STORAGE_NONE,
            syncComponentId: $componentId
        );
    }

    public function timeout(int $callerServiceId, string $action, array $parameters = []): TestResponse
    {
        return $this->sendUiEvent(
            componentId: $callerServiceId,
            event: 'timeout',
            action: $action,
            parameters: $parameters,
            storageMode: self::STORAGE_HEADER,
            syncComponentId: null
        );
    }

    private function sendUiEvent(
        int $componentId,
        string $event,
        string $action,
        array $parameters,
        string $storageMode,
        ?int $syncComponentId
    ): TestResponse {
        $payload = [
            'component_id' => $componentId,
            'event' => $event,
            'action' => $action,
            'parameters' => $parameters,
        ];

        $headers = [];
        $usimStorage = $this->memory->usimStorage();
        $storage_key = config('usim.front_store_key', 'my-app');
        if ($usimStorage !== '' && $storageMode === self::STORAGE_HEADER) {
            // In test client, encrypted header transport can be unreliable; use body fallback accepted by middleware.
            $payload[$storage_key] = $usimStorage;
        }

        if ($usimStorage !== '' && $storageMode === self::STORAGE_BODY) {
            // Input events in ui-renderer send storage in body field "storage".
            $payload['storage'] = $usimStorage;
        }

        $response = $this
            ->testWithClientCookie()
            ->postJson('/api/ui-event', $payload, $headers);

        $this->lastResponse = $response;

        if ($response->isOk()) {
            $this->ingest($response->json(), 'auto');

            // Some backend flows return only meta keys (e.g. storage) with no component deltas.
            // In that case, refresh current screen snapshot to keep the in-memory model browser-like.
            $responseJson = $response->json();
            if ($syncComponentId !== null && !$this->hasUiComponents($responseJson)) {
                $this->syncFromServer();
            }
        }

        return $response;
    }

    /** @return array<string, mixed> */
    public function componentData(string $name): array
    {
        return $this->mustGetComponent($name);
    }

    /** @return array<string, array<string, mixed>> */
    public function components(): array
    {
        return $this->memory->components();
    }

    public function dumpJson(?string $componentName = null): self
    {
        if ($componentName !== null) {
            dumpJson($this->componentData($componentName));
        } else {
            dumpJson($this->components());
        }

        return $this;
    }

    public function assertNoIssues(): self
    {
        expect($this->memory->issues())->toBe([]);
        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function issues(): array
    {
        return $this->memory->issues();
    }

    private function mustGetComponent(string $name): array
    {
        $component = $this->memory->componentByName($name);

        if ($component === null) {
            throw new RuntimeException("Component not found by name: {$name}");
        }

        return $component;
    }

    private function mustGetComponentId(string $name): int
    {
        $component = $this->mustGetComponent($name);
        $componentId = $component['_json_key'] ?? ($component['_id'] ?? null);

        if (!is_numeric($componentId)) {
            throw new RuntimeException("Component '{$name}' does not have a numeric component ID.");
        }

        return (int) $componentId;
    }

    private function testWithClientCookie(): TestCase
    {
        session()->put(UIStateManager::CLIENT_ID_COOKIE, $this->clientId);

        return $this->test
            ->withHeader(UIStateManager::CLIENT_ID_HEADER, $this->clientId)
            ->withUnencryptedCookie(UIStateManager::CLIENT_ID_COOKIE, $this->clientId);
    }

    private function syncFromServer(): void
    {
        if ($this->screenClass === '') {
            return;
        }

        $headers = [];
        $usimStorage = $this->memory->usimStorage();
        if ($usimStorage !== '') {
            $headers['X-USIM-Storage'] = $usimStorage;
        }

        $response = $this
            ->testWithClientCookie()
            ->getJson(screenApiUrl($this->screenClass, $this->screenQuery), $headers);

        if ($response->isOk()) {
            $payload = $response->json();
            if ($this->hasUiComponents($payload)) {
                $this->ingest($payload, 'initial');
            }
        }
    }

    private function hasUiComponents(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            if (isset($value['type'])) {
                return true;
            }
        }

        return false;
    }

    private function configurePersistentCacheForUiState(): void
    {
        if (self::$persistentCacheConfigured) {
            return;
        }

        // UI diff flow depends on previous UI snapshot being available across requests.
        // In tests, CACHE_STORE/SESSION_DRIVER=array are request-local; force persistent drivers for browser-like behavior.
        config([
            'cache.default' => 'file',
            'session.driver' => 'file',
        ]);
        Cache::setDefaultDriver('file');
        self::$persistentCacheConfigured = true;
    }
}
