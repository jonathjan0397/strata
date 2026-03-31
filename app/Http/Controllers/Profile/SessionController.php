<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $currentSessionId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'is_current' => $session->id === $currentSessionId,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'device' => $this->parseDevice($session->user_agent),
                'browser' => $this->parseBrowser($session->user_agent),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'last_active_ts' => $session->last_activity,
            ]);

        return Inertia::render('Profile/Sessions', [
            'sessions' => $sessions,
        ]);
    }

    /** Revoke a specific session (cannot revoke current). */
    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->withErrors(['session' => 'You cannot revoke your current session. Use logout instead.']);
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->delete();

        return back()->with('success', 'Session revoked.');
    }

    /** Revoke all sessions except the current one. */
    public function destroyOthers(Request $request): RedirectResponse
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'All other sessions have been revoked.');
    }

    private function parseDevice(?string $ua): string
    {
        if (! $ua) {
            return 'Unknown device';
        }

        if (preg_match('/iPhone/i', $ua)) {
            return 'iPhone';
        }
        if (preg_match('/iPad/i', $ua)) {
            return 'iPad';
        }
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/Windows/i', $ua)) {
            return 'Windows PC';
        }
        if (preg_match('/Macintosh/i', $ua)) {
            return 'Mac';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }

        return 'Unknown device';
    }

    private function parseBrowser(?string $ua): string
    {
        if (! $ua) {
            return 'Unknown browser';
        }

        if (preg_match('/Edg\//i', $ua)) {
            return 'Edge';
        }
        if (preg_match('/OPR\//i', $ua)) {
            return 'Opera';
        }
        if (preg_match('/Chrome\//i', $ua)) {
            return 'Chrome';
        }
        if (preg_match('/Firefox\//i', $ua)) {
            return 'Firefox';
        }
        if (preg_match('/Safari\//i', $ua)) {
            return 'Safari';
        }

        return 'Unknown browser';
    }
}
