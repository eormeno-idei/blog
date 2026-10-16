<?php
// @usim: feature="core", type="test"
namespace Tests\Support;

final class UiComponentRef
{
    private UiScenario $scenario;

    private string $name;

    public function __construct(UiScenario $scenario, string $name)
    {
        $this->scenario = $scenario;
        $this->name = $name;
    }

    public function click(array $parameters = []): self
    {
        $this->scenario->click($this->name, $parameters)->assertOk();
        return $this;
    }

    public function expect(string $field)
    {
        $component = $this->scenario->componentData($this->name);
        return expect($component[$field] ?? null);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->scenario->componentData($this->name);
    }

    public function dumpJson(): self
    {
        dumpJson($this->data());
        return $this;
    }

    public function id(): int
    {
        $component = $this->data();
        $id = $component['_json_key'] ?? ($component['_id'] ?? null);

        if (!is_numeric($id)) {
            throw new \RuntimeException("Component '{$this->name}' does not have a numeric component ID.");
        }

        return (int) $id;
    }
}
