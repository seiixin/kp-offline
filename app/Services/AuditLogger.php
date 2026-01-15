<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Minimal audit logger.
     * - Won’t crash your request if logging fails
     * - Writes to laravel.log under "audit" context
     *
     * If later you want DB-based audit logs, replace this implementation.
     */
    public static function record(string $action, string $entityType, string $entityId, array $meta = []): void
    {
        try {
            Log::channel(config('logging.default'))
                ->info('audit', [
                    'action'      => $action,
                    'entity_type' => $entityType,
                    'entity_id'   => $entityId,
                    'meta'        => $meta,
                ]);
        } catch (\Throwable $e) {
            // swallow to avoid breaking main flow
        }
    }
}
