<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'eligibleUsers' => User::whereDoesntHave('affiliate')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:affiliates,user_id'],
            'code' => ['nullable', 'string', 'max:20', 'alpha_num', 'unique:affiliates,code'],
            'status' => ['required', 'in:pending,active'],
            'commission_type' => ['required', 'in:percent,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'payout_threshold' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['code'] = $data['code'] ? strtoupper($data['code']) : strtoupper(Str::random(8));
        $data['balance'] = 0;
        $data['total_earned'] = 0;

        Affiliate::create($data);

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate created.');
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
            'code' => ['nullable', 'string', 'max:20', 'alpha_num', Rule::unique('affiliates', 'code')->ignore($affiliate->id)],
            'commission_type' => ['required', 'in:percent,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'payout_threshold' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        } else {
            unset($data['code']);
        }

        $affiliate->update($data);

        return back()->with('success', 'Affiliate settings updated.');
    }

    public function destroy(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->delete();

        return redirect()->route('admin.affiliates.index')
            ->with('success', 'Affiliate removed.');
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
            'status' => 'paid',
            'processed_at' => now(),
        ]);

        // Deduct from affiliate balance
        $payout->affiliate()->decrement('balance', $payout->amount);

        return back()->with('success', 'Payout marked as paid.');
    }
}
