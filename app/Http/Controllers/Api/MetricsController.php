<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $byStatus = Notification::query()
            ->selectRaw('status, channel, priority, count(*) as c')
            ->groupBy('status', 'channel', 'priority')
            ->get();

        $attempts = Notification::query()
            ->selectRaw('status, sum(attempts) as a')
            ->groupBy('status')
            ->pluck('a', 'status');

        $lines = [];
        $lines[] = '# HELP notifications_total Notifications partitioned by status, channel and priority.';
        $lines[] = '# TYPE notifications_total counter';
        foreach ($byStatus as $row) {
            $lines[] = sprintf(
                'notifications_total{status="%s",channel="%s",priority="%s"} %d',
                $row->status->value,
                $row->channel->value,
                $row->priority->value,
                (int) $row->c,
            );
        }

        $lines[] = '# HELP notifications_attempts_total Sum of send attempts partitioned by status.';
        $lines[] = '# TYPE notifications_attempts_total counter';
        foreach ($attempts as $status => $sum) {
            $lines[] = sprintf(
                'notifications_attempts_total{status="%s"} %d',
                $status instanceof \BackedEnum ? $status->value : (string) $status,
                (int) $sum,
            );
        }

        return response(
            implode("\n", $lines)."\n",
            200,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}
