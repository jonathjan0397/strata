<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainRegistrarService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainController extends Controller
{
    public function index()
    {
        $domains = auth()->user()
            ->domains()
            ->with('service')
            ->latest()
            ->get();

        return Inertia::render('Client/Domains/Index', [
            'domains' => $domains,
        ]);
    }

    public function show(Domain $domain)
    {
        abort_if($domain->user_id !== auth()->id(), 403);
        $domain->load('service');

        return Inertia::render('Client/Domains/Show', [
            'domain' => $domain,
        ]);
    }

    public function setNameservers(Request $request, Domain $domain)
    {
        abort_if($domain->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'nameservers' => ['required', 'array', 'min:2', 'max:6'],
            'nameservers.*' => ['required', 'string', 'max:255'],
        ]);

        $driver = DomainRegistrarService::driver($domain->registrar);
        $driver->setNameservers($domain->name, $data['nameservers']);

        $domain->update([
            'nameserver_1' => $data['nameservers'][0] ?? null,
            'nameserver_2' => $data['nameservers'][1] ?? null,
            'nameserver_3' => $data['nameservers'][2] ?? null,
            'nameserver_4' => $data['nameservers'][3] ?? null,
        ]);

        return back()->with('flash', ['success' => 'Nameservers updated.']);
    }

    public function toggleAutoRenew(Domain $domain)
    {
        abort_if($domain->user_id !== auth()->id(), 403);

        $domain->update(['auto_renew' => ! $domain->auto_renew]);

        return back()->with('flash', [
            'success' => $domain->auto_renew ? 'Auto-renew enabled.' : 'Auto-renew disabled.',
        ]);
    }

    /** Check availability — used by order checkout. */
    public function checkAvailability(Request $request)
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:253']]);

        try {
            $result = DomainRegistrarService::checkAvailability($data['domain']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['available' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
