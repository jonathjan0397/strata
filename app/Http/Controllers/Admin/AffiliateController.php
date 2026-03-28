<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Affiliates/Index', [
            'affiliates' => Affiliate::with('user')
                ->withCount('referrals')
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function show(Affiliate $affiliate): Response
    {
        $affiliate->load(['user', 'referrals.referredUser', 'referrals.order', 'payouts']);

        return Inertia::render('Admin/Affiliates/Show', [
            'affiliate' => $affiliate,
            'pendingPayouts' => $affiliate->payouts()->where('status', 'pending')->get(),
        ]);
    }

    public function update(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $data = $request->validate([
            'commission_type'  => ['required', 'in:percent,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'payout_threshold' => ['required', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ]);

        $affiliate->update($data);

        return back()->with('success', 'Affiliate settings updated.');
    }

    public function approve(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update(['status' => 'active']);

        return back()->with('success', 'Affiliate approved.');
    }

    public function deactivate(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update(['status' => 'inactive']);

        return back()->with('success', 'Affiliate deactivated.');
    }

    public function approveReferral(AffiliateReferral $referral): RedirectResponse
    {
        abort_unless($referral->status === 'pending', 422);

        $referral->update(['status' => 'approved']);

        // Credit commission to affiliate balance
        $referral->affiliate()->increment('balance', $referral->commission);
        $referral->affiliate()->increment('total_earned', $referral->commission);

        return back()->with('success', 'Referral approved and commission credited.');
    }

    public function approvePayout(AffiliatePayout $payout): RedirectResponse
    {
        $payout->update([
            'status'       => 'paid',
            'processed_at' => now(),
        ]);

        // Deduct from affiliate balance
        $payout->affiliate()->decrement('balance', $payout->amount);

        return back()->with('success', 'Payout marked as paid.');
    }
}
