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
                'open_tickets'    => $user->tickets()->whereIn('status', ['open', 'customer_reply'])->count(),
                'active_domains'  => $user->domains()->where('status', 'active')->count(),
            ],
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
            'recent_tickets' => $user->tickets()
                ->whereIn('status', ['open', 'customer_reply', 'answered'])
                ->latest('last_reply_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
