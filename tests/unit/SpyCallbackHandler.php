<?php

/**
 * Callback-capturing subclass of CallbackHandler for xtense unit tests.
 * Overrides add() to record registered callbacks without DB interaction.
 * Extends the real class so it satisfies the CallbackHandler type hint
 * in AbstractHandler's constructor.
 */
class SpyCallbackHandler extends CallbackHandler
{
    /** @var array<int, array{type: string, params: array}> */
    public array $calls = [];

    /** @param mixed $type @param mixed $params */
    public function add($type, $params): void
    {
        if (empty($params)) {
            return;
        }
        $this->calls[] = ['type' => $type, 'params' => $params];
    }

    /**
     * Returns all recorded calls for a given callback type.
     * @return array<int, array{type: string, params: array}>
     */
    public function getCallsForType(string $type): array
    {
        return array_values(
            array_filter($this->calls, fn(array $c): bool => $c['type'] === $type)
        );
    }

    public function hasCallForType(string $type): bool
    {
        return !empty($this->getCallsForType($type));
    }
}
