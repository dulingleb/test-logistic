<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $checks = [
            'postgres' => $this->probe(fn () => DB::connection()->getPdo()->query('SELECT 1')),
            'redis' => $this->probe(fn () => Redis::connection()->command('ping')),
            'rabbitmq' => $this->probe(function (): void {
                $connection = Queue::connection('rabbitmq');
                if (! $connection instanceof RabbitMQQueue) {
                    throw new \RuntimeException('rabbitmq connection not active');
                }
                $connection->declareQueue('notifications.transactional');
            }),
        ];

        $ready = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $ready ? 'ready' : 'degraded',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function probe(callable $check): array
    {
        try {
            $check();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
