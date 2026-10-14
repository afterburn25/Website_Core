<?php
declare(strict_types=1);

namespace Core\Events;

/**
 * In-process event dispatcher. Listeners run inside the caller's transaction
 * boundary, so a throwing listener aborts the operation — transaction-aware
 * dispatch by construction. For effects that must only happen after commit,
 * producers write to the jobs/outbox table instead (see JobQueue).
 */
final class Dispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(string $event, array $payload = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /** @return array<string, int> event => listener count (diagnostics) */
    public function registered(): array
    {
        return array_map('count', $this->listeners);
    }
}
