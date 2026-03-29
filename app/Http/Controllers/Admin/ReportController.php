<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $now = now();

        // ── MRR / ARR ────────────────────────────────────────────────────────
        // Sum of all active services' monthly-equivalent recurring amount
        $mrr = Service::where('status', 'active')
            ->join('products', 'services.product_id', '=', 'products.id')
            ->select(DB::raw("
                SUM(CASE
                    WHEN products.billing_cycle = 'monthly'      THEN services.amount
                    WHEN products.billing_cycle = 'quarterly'    THEN services.amount / 3
                    WHEN products.billing_cycle = 'semi_annual'  THEN services.amount / 6
                    WHEN products.billing_cycle = 'annual'       THEN services.amount / 12
                    WHEN products.billing_cycle = 'biennial'     THEN services.amount / 24
                    WHEN products.billing_cycle = 'triennial'    THEN services.amount / 36
                    ELSE 0
                END) as mrr
            "))
            ->value('mrr') ?? 0;

        $arr = $mrr * 12;

        // ── Revenue by month (last 12 months) ────────────────────────────────
        $revenueByMonth = Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as invoice_count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Fill in all 12 months (including zeros)
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = $now->copy()->subMonths($i)->format('Y-m');
            $months[] = [
                'month'         => $key,
                'label'         => $now->copy()->subMonths($i)->format('M Y'),
                'revenue'       => (float) ($revenueByMonth[$key]->revenue ?? 0),
                'invoice_count' => (int) ($revenueByMonth[$key]->invoice_count ?? 0),
            ];
        }

        // ── This month vs last month ──────────────────────────────────────────
        $thisMonth = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->sum('total');

        $lastMonth = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ])
            ->sum('total');

        $revenueGrowth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : null;

        // ── Outstanding / overdue ─────────────────────────────────────────────
        $unpaidTotal   = Invoice::where('status', 'unpaid')->sum('amount_due');
        $overdueTotal  = Invoice::where('status', 'overdue')->sum('amount_due');
        $unpaidCount   = Invoice::where('status', 'unpaid')->count();
        $overdueCount  = Invoice::where('status', 'overdue')->count();

        // ── Client stats ─────────────────────────────────────────────────────
        $totalClients  = User::role('client')->count();
        $newThisMonth  = User::role('client')
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->count();
        $activeClients = Service::where('status', 'active')
            ->distinct('user_id')
            ->count('user_id');

        // ── New clients by month (last 6) ─────────────────────────────────────
        $clientsByMonth = User::role('client')
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $clientMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = $now->copy()->subMonths($i)->format('Y-m');
            $clientMonths[] = [
                'label' => $now->copy()->subMonths($i)->format('M Y'),
                'count' => (int) ($clientsByMonth[$key]->count ?? 0),
            ];
        }

        // ── Top clients by lifetime revenue ───────────────────────────────────
        $topClients = Invoice::where('status', 'paid')
            ->select('user_id', DB::raw('SUM(total) as lifetime_revenue'), DB::raw('COUNT(*) as invoice_count'))
            ->groupBy('user_id')
            ->orderByDesc('lifetime_revenue')
            ->with('user:id,name,email')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'user'             => $r->user,
                'lifetime_revenue' => (float) $r->lifetime_revenue,
                'invoice_count'    => $r->invoice_count,
            ]);

        // ── Service status breakdown ──────────────────────────────────────────
        $serviceStats = Service::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Support stats ─────────────────────────────────────────────────────
        $openTickets    = SupportTicket::where('status', 'open')->count();
        $avgResolutionHours = SupportTicket::where('status', 'closed')
            ->whereNotNull('updated_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours'))
            ->value('avg_hours');

        // ── Satisfaction ratings ──────────────────────────────────────────────
        $ratedTickets = SupportTicket::whereNotNull('rating');

        $avgRating = $ratedTickets->clone()->avg('rating');

        // Distribution: count per star 1-5
        $ratingDistribution = $ratedTickets->clone()
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill in all 5 stars (including zeros)
        $ratingDist = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratingDist[$i] = (int) ($ratingDistribution[$i] ?? 0);
        }

        // Per-staff breakdown (staff user → avg rating on tickets they replied to, that are now closed+rated)
        $perStaff = SupportTicket::whereNotNull('rating')
            ->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as ticket_count'))
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->map(fn ($r) => [
                'staff'        => $r->assignedTo?->name ?? 'Unassigned',
                'avg_rating'   => round((float) $r->avg_rating, 1),
                'ticket_count' => (int) $r->ticket_count,
            ])
            ->sortByDesc('avg_rating')
            ->values();

        $totalRated = $ratedTickets->clone()->count();

        return Inertia::render('Admin/Reports/Index', [
            'mrr'              => round($mrr, 2),
            'arr'              => round($arr, 2),
            'revenueByMonth'   => $months,
            'thisMonth'        => round($thisMonth, 2),
            'lastMonth'        => round($lastMonth, 2),
            'revenueGrowth'    => $revenueGrowth,
            'unpaidTotal'      => round($unpaidTotal, 2),
            'overdueTotal'     => round($overdueTotal, 2),
            'unpaidCount'      => $unpaidCount,
            'overdueCount'     => $overdueCount,
            'totalClients'     => $totalClients,
            'newThisMonth'     => $newThisMonth,
            'activeClients'    => $activeClients,
            'clientsByMonth'   => $clientMonths,
            'topClients'       => $topClients,
            'serviceStats'     => $serviceStats,
            'openTickets'        => $openTickets,
            'avgResolutionHours' => $avgResolutionHours ? round($avgResolutionHours, 1) : null,
            'avgRating'          => $avgRating ? round((float) $avgRating, 2) : null,
            'ratingDist'         => $ratingDist,
            'totalRated'         => $totalRated,
            'ratingsByStaff'     => $perStaff,
        ]);
    }
}
