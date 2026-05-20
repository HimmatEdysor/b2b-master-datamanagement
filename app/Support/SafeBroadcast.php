<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class SafeBroadcast
{
    /**
     * Dispatch a broadcast event without failing the request if Reverb/Pusher is down.
     */
    public static function dispatch(ShouldBroadcast $event): void
    {
        $driver = config('broadcasting.default');

        if ($driver === null || $driver === 'null') {
            return;
        }

        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed (is Reverb running?). Message was saved.', [
                'driver' => $driver,
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
