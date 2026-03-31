<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $affiliate = $user->affiliate()->with(['referrals', 'payouts'])->first();

        return Inertia::render('Client/Affiliate/Dashboard', [
            'affiliate' => $affiliate,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->affiliate()->exists()) {
            return back()->with('error', 'You already have an affiliate account.');
        }

        $settings = Setting::allKeyed();

        Affiliate::create([
            'user_id' => $user->id,
            'code' => strtoupper(Str::random(8)),
            'status' => 'pending',
            'commission_type' => $settings['affiliate_default_commission_type'] ?? 'percent',
            'commission_value' => $settings['affiliate_default_commission_value'] ?? 10,
            'balance' => 0,
            'total_earned' => 0,
            'payout_threshold' => $settings['affiliate_default_payout_threshold'] ?? 50,
        ]);

        return back()->with('success', 'Affiliate account created! It will be reviewed and activated shortly.');
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $affiliate = $user->affiliate;

        if (! $affiliate || $affiliate->status !== 'active') {
            return back()->with('error', 'Affiliate account is not active.');
        }

        $request->validate([
            'method' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($affiliate->balance < $affiliate->payout_threshold) {
            return back()->with('error', "Minimum payout threshold is \${$affiliate->payout_threshold}. Your balance is \${$affiliate->balance}.");
        }

        if ($affiliate->payouts()->where('status', 'pending')->exists()) {
            return back()->with('error', 'You already have a pending payout request.');
        }

        AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'amount' => $affiliate->balance,
            'method' => $request->method,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Payout request submitted. Our team will process it shortly.');
    }
}
