<?php
declare(strict_types=1);

namespace Core\Jobs;

use Core\Container;
use Core\Database\Connection;

/**
 * Bounded, single-pass job runner used by cron.php. Claims a batch under a
 * worker lease, invokes registered handlers, and records results. A job that
 * throws is retried with backoff; a job whose worker died mid-run returns to
 * 'uncertain' on the next claim and is retried — handlers must be idempotent
 * (at-least-once delivery, never exactly-once external effects).
 */
final class Runner
{
    public function __construct(private Container $container) {}

    public function run(int $batch = 10, int $secondsBudget = 25): int
    {
        $queue = $this->container->get(JobQueue::class);
        $registry = $this->container->has(\Core\Modules\ModuleRegistry::class)
            ? $this->container->get(\Core\Modules\ModuleRegistry::class)
            : null;
        $handlers = ($registry?->jobHandlers() ?? []) + $this->coreHandlers();

        $workerId = gethostname() . ':' . getmypid() . ':' . bin2hex(random_bytes(4));
        $deadline = time() + $secondsBudget;
        $processed = 0;

        foreach ($queue->claim($workerId, $batch) as $job) {
            if (time() > $deadline) {
                $queue->markUncertain((int) $job['id']);
                continue;
            }
            $handler = $handlers[$job['type']] ?? null;
            if ($handler === null) {
                $queue->fail((int) $job['id'], 'no handler registered for ' . $job['type'], 1);
                continue;
            }
            try {
                $handler(json_decode((string) $job['payload'], true) ?: [], $this->container);
                $queue->complete((int) $job['id']);
                $processed++;
            } catch (\Throwable $e) {
                $queue->fail((int) $job['id'], $e->getMessage());
            }
        }
        return $processed;
    }

    /** @return array<string, callable> */
    private function coreHandlers(): array
    {
        return [
            'core.heartbeat' => function (array $payload, Container $c): void {
                $c->get(Connection::class); // touch DB; proves runner works
            },
            'mail.send' => function (array $payload, Container $c): void {
                $c->get(\Core\Mail\SystemMail::class)->flush((int) ($payload['limit'] ?? 10));
            },
            'license.validate' => function (array $payload, Container $c): void {
                $c->get(\Core\Jobs\LicenseValidationJob::class)->handle($payload);
            },
            'core.backup' => function (array $payload, Container $c): void {
                $config = $c->get(\Core\Config::class);
                $storage = (string) $config->get('paths.storage', WC_ROOT . '/storage');
                [$path, $error] = (new \Core\Backup\BackupService(
                    $c->get(Connection::class), $storage . '/backups', $storage . '/staging', WC_ROOT, $storage
                ))->create();
                if ($error !== null) throw new \RuntimeException($error);
            },
        ];
    }
}
