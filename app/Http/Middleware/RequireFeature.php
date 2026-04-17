<?php

namespace App\Http\Middleware;

use App\Services\StrataLicense;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    /**
     * Metadata for each premium feature — used by the Upsell page.
     */
    private const FEATURES = [
        'workflows' => [
            'key'     => 'workflows',
            'title'   => 'Workflows Automation',
            'tagline' => 'Automate repetitive tasks with powerful event-driven workflows.',
            'bullets' => [
                'Trigger actions on invoice, ticket, and service events',
                'Send custom email notifications automatically',
                'Update service states without manual intervention',
                'Build complex multi-step automation chains',
            ],
            'icon' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
        ],
        'affiliates' => [
            'key'     => 'affiliates',
            'title'   => 'Affiliate Program',
            'tagline' => 'Grow your business with a built-in referral and affiliate system.',
            'bullets' => [
                'Track referrals and commissions automatically',
                'Flexible payout thresholds and approval workflows',
                'Partner-facing affiliate dashboard',
                'Detailed referral attribution reporting',
            ],
            'icon' => 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244',
        ],
        'advanced_reports' => [
            'key'     => 'advanced_reports',
            'title'   => 'Advanced Reporting',
            'tagline' => 'Deep financial and operational insights for your business.',
            'bullets' => [
                'MRR/ARR trends and revenue forecasting',
                'Client acquisition and churn analytics',
                'Support performance metrics',
                'Monthly revenue breakdowns by product',
            ],
            'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        ],
        'quotes' => [
            'key'     => 'quotes',
            'title'   => 'Quotes & Proposals',
            'tagline' => 'Win more business with professional quotes and proposals.',
            'bullets' => [
                'Create and send professional branded quotes',
                'Clients accept online — auto-converts to invoice',
                'Track quote status from draft to converted',
                'Full quote lifecycle management',
            ],
            'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
        ],
        'audit_log' => [
            'key'     => 'audit_log',
            'title'   => 'Audit Log',
            'tagline' => 'Full visibility into every action taken in your panel.',
            'bullets' => [
                'Track every admin and staff action with timestamps',
                'Filter by user, action type, and date range',
                'Immutable tamper-evident event history',
                'Essential for compliance and accountability',
            ],
            'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
        ],
        'client_groups' => [
            'key'     => 'client_groups',
            'title'   => 'Client Groups',
            'tagline' => 'Organize clients into groups for better management and segmentation.',
            'bullets' => [
                'Create custom client segments and categories',
                'Apply bulk actions to entire groups at once',
                'Segment clients for targeted communications',
                'Better organization for large client bases',
            ],
            'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
        ],
    ];

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // If no license server is configured, all features are available.
        if (! config('strata.license_server_url')) {
            return $next($request);
        }

        if (StrataLicense::hasFeature($feature)) {
            return $next($request);
        }

        // Non-GET requests (form submits, Inertia POSTs): redirect back with error.
        if (! $request->isMethod('GET')) {
            return back()->with('flash', ['error' => 'This feature requires a license upgrade.']);
        }

        $featureMeta = self::FEATURES[$feature] ?? [
            'key'     => $feature,
            'title'   => ucwords(str_replace('_', ' ', $feature)),
            'tagline' => 'This feature requires a license upgrade.',
            'bullets' => [],
            'icon'    => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
        ];

        return Inertia::render('Admin/Upsell', [
            'feature'   => $featureMeta,
        ])->toResponse($request);
    }
}
