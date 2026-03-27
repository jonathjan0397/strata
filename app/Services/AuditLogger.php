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
        string|null $actorType = null,
    ): AuditLog {
        $uid = $userId ?? Auth::id();

        if ($actorType === null) {
            if ($uid === null) {
                $actorType = 'system';
            } else {
                $actor = ($uid === Auth::id()) ? Auth::user() : \App\Models\User::find($uid);
                $actorType = $actor?->hasAnyRole(['super-admin', 'admin', 'staff']) ? 'admin' : 'client';
            }
        }

        return AuditLog::create([
            'user_id'     => $uid,
            'actor_type'  => $actorType,
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'details'     => $details ?: null,
            'ip_address'  => Request::ip(),
            'created_at'  => now(),
        ]);
    }
}
