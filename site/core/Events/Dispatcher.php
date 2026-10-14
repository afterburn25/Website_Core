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

    /**
     * Lifecycle events. Listeners are package code: one failing listener
     * must not abort the operation that fired the event, so each call is
     * isolated and errors are collected, not thrown.
     * @return list<string> listener error messages (empty when clean)
     */
    public function dispatch(string $event, array $payload = []): array
    {
        $errors = [];
        foreach ($this->listeners[$event] ?? [] as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                $errors[] = $event . ': ' . $e->getMessage();
            }
        }
        return $errors;
    }

    /** @return array<string, int> event => listener count (diagnostics) */
    public function registered(): array
    {
        return array_map('count', $this->listeners);
    }
}
