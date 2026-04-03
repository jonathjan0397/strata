<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->input('period', 'last_12_months');
        $year   = (int) $request->input('year', now()->year);
        $month  = $request->input('month', now()->format('Y-m'));

        [$start, $end] = $this->resolvePeriod($period, $year, $month);
        $useDaily = in_array($period, ['current_month', 'last_month', 'specific_month']);

        $now = now();

        // ── MRR / ARR ────────────────────────────────────────────────────────
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

        // ── Revenue chart (period-aware) ──────────────────────────────────────
        $revenueChart = $useDaily
            ? $this->buildDailyChart($start, $end)
            : $this->buildMonthlyChart($start, $end);

        // ── Period totals ─────────────────────────────────────────────────────
        $periodRevenue = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total');

        $periodInvoiceCount = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->count();

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
        $unpaidTotal  = Invoice::where('status', 'unpaid')->sum('amount_due');
        $overdueTotal = Invoice::where('status', 'overdue')->sum('amount_due');
        $unpaidCount  = Invoice::where('status', 'unpaid')->count();
        $overdueCount = Invoice::where('status', 'overdue')->count();

        // ── Client stats ─────────────────────────────────────────────────────
        $totalClients  = User::role('client')->count();
        $newThisMonth  = User::role('client')->where('created_at', '>=', $now->copy()->startOfMonth())->count();
        $activeClients = Service::where('status', 'active')->distinct('user_id')->count('user_id');

        // ── New clients by month (last 6) ─────────────────────────────────────
        $clientsByMonth = User::role('client')
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as count'))
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
        $openTickets        = SupportTicket::where('status', 'open')->count();
        $avgResolutionHours = SupportTicket::where('status', 'closed')
            ->whereNotNull('updated_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours'))
            ->value('avg_hours');

        // ── Satisfaction ratings ──────────────────────────────────────────────
        $ratedTickets = SupportTicket::whereNotNull('rating');
        $avgRating    = $ratedTickets->clone()->avg('rating');

        $ratingDistribution = $ratedTickets->clone()
            ->select('rating', DB::raw('COUNT(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $ratingDist = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratingDist[$i] = (int) ($ratingDistribution[$i] ?? 0);
        }

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
            // Period selector state
            'period'       => $period,
            'year'         => $year,
            'month'        => $month,
            'periodLabel'  => $this->periodLabel($period, $year, $month),
            'periodStart'  => $start->toDateString(),
            'periodEnd'    => $end->toDateString(),
            'availableYears' => $this->availableYears(),
            'availableMonths' => $this->availableMonths(),
            // Revenue
            'mrr'              => round($mrr, 2),
            'arr'              => round($arr, 2),
            'revenueChart'     => $revenueChart,
            'chartIsDaily'     => $useDaily,
            'periodRevenue'    => round($periodRevenue, 2),
            'periodInvoiceCount' => $periodInvoiceCount,
            'thisMonth'        => round($thisMonth, 2),
            'lastMonth'        => round($lastMonth, 2),
            'revenueGrowth'    => $revenueGrowth,
            'unpaidTotal'      => round($unpaidTotal, 2),
            'overdueTotal'     => round($overdueTotal, 2),
            'unpaidCount'      => $unpaidCount,
            'overdueCount'     => $overdueCount,
            // Clients
            'totalClients'   => $totalClients,
            'newThisMonth'   => $newThisMonth,
            'activeClients'  => $activeClients,
            'clientsByMonth' => $clientMonths,
            'topClients'     => $topClients,
            // Services / Support
            'serviceStats'        => $serviceStats,
            'openTickets'         => $openTickets,
            'avgResolutionHours'  => $avgResolutionHours ? round($avgResolutionHours, 1) : null,
            'avgRating'           => $avgRating ? round((float) $avgRating, 2) : null,
            'ratingDist'          => $ratingDist,
            'totalRated'          => $totalRated,
            'ratingsByStaff'      => $perStaff,
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        $period = $request->input('period', 'last_12_months');
        $year   = (int) $request->input('year', now()->year);
        $month  = $request->input('month', now()->format('Y-m'));
        $type   = $request->input('type', 'invoices'); // invoices | summary

        [$start, $end] = $this->resolvePeriod($period, $year, $month);

        $prefix   = Setting::get('invoice_prefix', 'INV-');
        $label    = $this->periodLabel($period, $year, $month);
        $filename = 'strata-' . ($type === 'summary' ? 'summary' : 'invoices') . '-' . str_replace([' ', '/'], '-', strtolower($label)) . '.csv';

        return response()->streamDownload(function () use ($start, $end, $prefix, $type) {
            $out = fopen('php://output', 'w');

            if ($type === 'summary') {
                // Monthly summary — useful for accountants reconciling totals
                fputcsv($out, ['Period', 'Invoices Paid', 'Subtotal', 'Tax', 'Total Revenue']);

                $rows = Invoice::where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $end])
                    ->select(
                        DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as period"),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(subtotal) as subtotal'),
                        DB::raw('SUM(tax) as tax'),
                        DB::raw('SUM(total) as total')
                    )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();

                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->period,
                        $row->count,
                        number_format($row->subtotal, 2, '.', ''),
                        number_format($row->tax, 2, '.', ''),
                        number_format($row->total, 2, '.', ''),
                    ]);
                }

                // Totals row
                fputcsv($out, []);
                fputcsv($out, [
                    'TOTAL',
                    $rows->sum('count'),
                    number_format($rows->sum('subtotal'), 2, '.', ''),
                    number_format($rows->sum('tax'), 2, '.', ''),
                    number_format($rows->sum('total'), 2, '.', ''),
                ]);
            } else {
                // Detailed invoice export — compatible with QuickBooks, Xero, Wave, FreshBooks CSV import
                fputcsv($out, [
                    'Invoice Number', 'Date Paid', 'Due Date', 'Client Name', 'Client Email',
                    'Description', 'Subtotal', 'Tax', 'Total', 'Payment Method', 'Notes',
                ]);

                Invoice::where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $end])
                    ->with('user:id,name,email')
                    ->orderBy('paid_at')
                    ->chunk(500, function ($invoices) use ($out, $prefix) {
                        foreach ($invoices as $inv) {
                            fputcsv($out, [
                                $prefix . $inv->id,
                                $inv->paid_at?->format('Y-m-d') ?? '',
                                $inv->due_date?->format('Y-m-d') ?? '',
                                $inv->user?->name ?? '',
                                $inv->user?->email ?? '',
                                $inv->notes ?? '',
                                number_format($inv->subtotal, 2, '.', ''),
                                number_format($inv->tax, 2, '.', ''),
                                number_format($inv->total, 2, '.', ''),
                                $inv->payment_method ?? '',
                                $inv->notes ?? '',
                            ]);
                        }
                    });
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolvePeriod(string $period, int $year, string $month): array
    {
        $now = now();

        return match ($period) {
            'current_month'  => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month'     => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'specific_month' => [Carbon::parse($month)->startOfMonth(), Carbon::parse($month)->endOfMonth()],
            'ytd'            => [$now->copy()->startOfYear(), $now->copy()->endOfMonth()],
            'specific_year'  => [Carbon::createFromDate($year)->startOfYear(), Carbon::createFromDate($year)->endOfYear()],
            'last_year'      => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all_time'       => [Carbon::createFromDate(2000, 1, 1)->startOfDay(), $now->copy()->endOfMonth()],
            default          => [$now->copy()->subMonths(11)->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function buildMonthlyChart(Carbon $start, Carbon $end): array
    {
        $rows = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as period"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as invoice_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $result = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $result[] = [
                'period'        => $key,
                'label'         => $cursor->format('M Y'),
                'revenue'       => (float) ($rows[$key]->revenue ?? 0),
                'invoice_count' => (int) ($rows[$key]->invoice_count ?? 0),
            ];
            $cursor->addMonth();
        }

        return $result;
    }

    private function buildDailyChart(Carbon $start, Carbon $end): array
    {
        $rows = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m-%d') as period"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as invoice_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $result = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $result[] = [
                'period'        => $key,
                'label'         => $cursor->format('M d'),
                'revenue'       => (float) ($rows[$key]->revenue ?? 0),
                'invoice_count' => (int) ($rows[$key]->invoice_count ?? 0),
            ];
            $cursor->addDay();
        }

        return $result;
    }

    private function periodLabel(string $period, int $year, string $month): string
    {
        return match ($period) {
            'current_month'  => 'This Month (' . now()->format('M Y') . ')',
            'last_month'     => 'Last Month (' . now()->subMonth()->format('M Y') . ')',
            'specific_month' => Carbon::parse($month)->format('F Y'),
            'ytd'            => 'Year to Date (' . now()->year . ')',
            'specific_year'  => (string) $year,
            'last_year'      => 'Last Year (' . (now()->year - 1) . ')',
            'all_time'       => 'All Time',
            default          => 'Last 12 Months',
        };
    }

    private function availableYears(): array
    {
        $earliest = Invoice::where('status', 'paid')->min(DB::raw('YEAR(paid_at)'));
        $earliest = $earliest ?? now()->year;
        $years    = [];
        for ($y = now()->year; $y >= $earliest; $y--) {
            $years[] = $y;
        }

        return $years;
    }

    private function availableMonths(): array
    {
        // Last 24 months as YYYY-MM strings for the month picker
        $months = [];
        for ($i = 0; $i < 24; $i++) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        return $months;
    }
}
