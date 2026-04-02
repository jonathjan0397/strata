<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Client/Dashboard', [
            'stats' => [
                'active_services' => $user->services()->where('status', 'active')->count(),
                'unpaid_invoices' => $user->invoices()->where('status', 'unpaid')->count(),
                'open_tickets' => $user->tickets()->whereIn('status', ['open', 'customer_reply'])->count(),
                'active_domains' => $user->domains()->where('status', 'active')->count(),
            ],
            'all_services' => $user->services()
                ->with('product')
                ->whereIn('status', ['active', 'suspended', 'pending'])
                ->orderByRaw("FIELD(status,'active','pending','suspended')")
                ->orderBy('next_due_date')
                ->get(),
            'services_due' => $user->services()
                ->with('product')
                ->where('status', 'active')
                ->where('next_due_date', '<=', now()->addDays(30))
                ->orderBy('next_due_date')
                ->limit(5)
                ->get(),
            'unpaid_invoices' => $user->invoices()
                ->where('status', 'unpaid')
                ->latest('due_date')
                ->limit(5)
                ->get(),
            'billing_history' => $user->invoices()
                ->where('status', 'paid')
                ->latest('paid_at')
                ->limit(6)
                ->get(['id', 'total', 'paid_at', 'date']),
            'recent_tickets' => $user->tickets()
                ->whereIn('status', ['open', 'customer_reply', 'answered'])
                ->latest('last_reply_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
