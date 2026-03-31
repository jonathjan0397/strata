<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_clients' => User::role('client')->count(),
                'active_services' => Service::where('status', 'active')->count(),
                'open_invoices' => Invoice::where('status', 'unpaid')->count(),
                'overdue_invoices' => Invoice::where('status', 'unpaid')
                    ->where('due_date', '<', now())
                    ->count(),
                'open_tickets' => SupportTicket::whereIn('status', ['open', 'customer_reply'])->count(),
                'mrr' => Service::where('status', 'active')
                    ->where('billing_cycle', 'monthly')
                    ->sum('amount'),
            ],
            'recent_orders' => Order::with('user')
                ->latest()
                ->limit(5)
                ->get(),
            'recent_tickets' => SupportTicket::with('user')
                ->whereIn('status', ['open', 'customer_reply'])
                ->latest('last_reply_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
