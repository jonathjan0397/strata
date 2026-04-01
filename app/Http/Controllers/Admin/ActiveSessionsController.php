<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ActiveSessionsController extends Controller
{
    public function index(Request $request): Response
    {
        $currentSessionId = $request->session()->getId();

        // Fetch all authenticated sessions, join users + highest-priority role
        $rows = DB::table('sessions')
            ->whereNotNull('sessions.user_id')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            // Sub-select: pick the single highest-priority role for each user
            ->leftJoinSub(
                DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->select(
                        'model_has_roles.model_id as user_id',
                        DB::raw("MAX(CASE
                            WHEN roles.name = 'super-admin' THEN 4
                            WHEN roles.name = 'admin'       THEN 3
                            WHEN roles.name = 'staff'       THEN 2
                            WHEN roles.name = 'client'      THEN 1
                            ELSE 0 END) as role_rank"),
                        DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(roles.name ORDER BY
                            CASE
                                WHEN roles.name = 'super-admin' THEN 4
                                WHEN roles.name = 'admin'       THEN 3
                                WHEN roles.name = 'staff'       THEN 2
                                WHEN roles.name = 'client'      THEN 1
                                ELSE 0 END DESC), ',', 1) as role_name")
                    )
                    ->groupBy('model_has_roles.model_id'),
                'ur',
                'ur.user_id',
                '=',
                'sessions.user_id'
            )
            ->select(
                'sessions.id as session_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.id as user_id',
                'users.name',
                'users.email',
                DB::raw("COALESCE(ur.role_name, 'client') as role")
            )
            ->orderByDesc('sessions.last_activity')
            ->get();

        $sessions = $rows->map(fn ($row) => [
            'session_id'  => $row->session_id,
            'is_current'  => $row->session_id === $currentSessionId,
            'user_id'     => $row->user_id,
            'name'        => $row->name,
            'email'       => $row->email,
            'role'        => $row->role,
            'ip_address'  => $row->ip_address,
            'device'      => $this->parseDevice($row->user_agent),
            'browser'     => $this->parseBrowser($row->user_agent),
            'last_active' => Carbon::createFromTimestamp($row->last_activity)->diffForHumans(),
            'last_active_ts' => $row->last_activity,
        ]);

        $counts = [
            'total'  => $sessions->count(),
            'admin'  => $sessions->whereIn('role', ['super-admin', 'admin'])->count(),
            'staff'  => $sessions->where('role', 'staff')->count(),
            'client' => $sessions->where('role', 'client')->count(),
        ];

        return Inertia::render('Admin/ActiveSessions', [
            'sessions' => $sessions->values(),
            'counts'   => $counts,
        ]);
    }

    /** Force-revoke any session (admins cannot revoke their own current session). */
    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->withErrors(['session' => 'You cannot revoke your own current session.']);
        }

        DB::table('sessions')->where('id', $sessionId)->delete();

        return back()->with('success', 'Session revoked.');
    }

    /** Revoke all sessions for a specific user (except current admin session). */
    public function destroyUser(Request $request, int $userId): RedirectResponse
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'All sessions for that user have been revoked.');
    }

    private function parseDevice(?string $ua): string
    {
        if (! $ua) return 'Unknown';
        if (preg_match('/iPhone/i', $ua))     return 'iPhone';
        if (preg_match('/iPad/i', $ua))       return 'iPad';
        if (preg_match('/Android/i', $ua))    return 'Android';
        if (preg_match('/Windows/i', $ua))    return 'Windows PC';
        if (preg_match('/Macintosh/i', $ua))  return 'Mac';
        if (preg_match('/Linux/i', $ua))      return 'Linux';
        return 'Unknown';
    }

    private function parseBrowser(?string $ua): string
    {
        if (! $ua) return 'Unknown';
        if (preg_match('/Edg\//i', $ua))      return 'Edge';
        if (preg_match('/OPR\//i', $ua))      return 'Opera';
        if (preg_match('/Chrome\//i', $ua))   return 'Chrome';
        if (preg_match('/Firefox\//i', $ua))  return 'Firefox';
        if (preg_match('/Safari\//i', $ua))   return 'Safari';
        return 'Unknown';
    }
}
