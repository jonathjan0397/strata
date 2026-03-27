<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        Model|null $target = null,
        array $details = [],
        int|null $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'     => $userId ?? Auth::id(),
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'details'     => $details ?: null,
            'ip_address'  => Request::ip(),
            'created_at'  => now(),
        ]);
    }
}
