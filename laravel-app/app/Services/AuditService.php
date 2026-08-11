<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(string $event, ?Model $model = null, array $old = [], array $new = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'actor_id' => auth()->id(), 'event' => $event,
            'auditable_type' => $model?->getMorphClass(), 'auditable_id' => $model?->getKey(),
            'old_values' => $old ?: null, 'new_values' => $new ?: null,
            'ip_address' => $request?->ip(), 'user_agent' => mb_substr((string) $request?->userAgent(), 0, 1000),
        ]);
    }
}
