<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PDO;
use PDOException;
use Tests\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

/**
 * Base class for tests that hit the real services from docker-compose.
 *
 * Responsibilities:
 *   - Skip the whole class when the broker / DB are unreachable so the
 *     suite degrades gracefully outside the containerized environment.
 *   - Ensure the dedicated 'notifications_test' database exists before
 *     RefreshDatabase tries to run migrations against it.
 *   - Purge the notification queues between tests so leftover messages
 *     from a prior test don't leak in.
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,string> */
    protected array $queuesToPurge = [
        'notifications.transactional',
        'notifications.marketing',
        'notifications.dlq',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::ensureRabbitMqReachable();
        self::ensureTestDatabaseExists();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->purgeQueues();
    }

    private static function ensureRabbitMqReachable(): void
    {
        $host = (string) env('RABBITMQ_HOST', 'rabbitmq');
        $port = (int) env('RABBITMQ_PORT', 5672);

        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($host, $port, $errno, $errstr, 1.5);
        if ($sock === false) {
            self::markTestSkipped("RabbitMQ unreachable at {$host}:{$port} ({$errstr}). Run `make up` first.");
        }
        fclose($sock);
    }

    private static function ensureTestDatabaseExists(): void
    {
        $host = (string) env('DB_HOST', 'postgres');
        $port = (int) env('DB_PORT', 5432);
        $user = (string) env('DB_USERNAME', 'notifications');
        $password = (string) env('DB_PASSWORD', 'secret');
        $database = (string) env('DB_DATABASE', 'notifications_test');

        try {
            $root = new PDO(
                "pgsql:host={$host};port={$port};dbname=postgres",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $e) {
            self::markTestSkipped("Postgres unreachable at {$host}:{$port}: {$e->getMessage()}");
        }

        $stmt = $root->prepare('SELECT 1 FROM pg_database WHERE datname = :n');
        $stmt->execute(['n' => $database]);
        if ($stmt->fetchColumn() === false) {
            // CREATE DATABASE cannot be parameterized; identifier is sourced
            // from env, not user input, so this is safe.
            $root->exec('CREATE DATABASE "'.str_replace('"', '""', $database).'"');
        }
    }

    private function purgeQueues(): void
    {
        $queue = Queue::connection('rabbitmq');
        if (! $queue instanceof RabbitMQQueue) {
            return;
        }

        foreach ($this->queuesToPurge as $name) {
            try {
                $queue->declareQueue($name);
                $queue->purge($name);
            } catch (\Throwable) {
                // Queue may not exist yet — purge is best-effort.
            }
        }
    }
}
