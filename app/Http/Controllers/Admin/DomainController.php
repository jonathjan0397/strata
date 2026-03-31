<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainRegistrarService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $domains = Domain::with(['user', 'service'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Domains/Index', [
            'domains' => $domains,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function show(Domain $domain)
    {
        $domain->load(['user', 'service']);

        return Inertia::render('Admin/Domains/Show', [
            'domain' => $domain,
        ]);
    }

    public function syncNameservers(Request $request, Domain $domain)
    {
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

    public function setLock(Request $request, Domain $domain)
    {
        $data = $request->validate(['locked' => ['required', 'boolean']]);

        $driver = DomainRegistrarService::driver($domain->registrar);
        $driver->setLock($domain->name, $data['locked']);

        $domain->update(['locked' => $data['locked']]);

        return back()->with('flash', ['success' => $data['locked'] ? 'Domain locked.' : 'Domain unlocked.']);
    }

    public function setPrivacy(Request $request, Domain $domain)
    {
        $data = $request->validate(['privacy' => ['required', 'boolean']]);

        $driver = DomainRegistrarService::driver($domain->registrar);
        $driver->setPrivacy($domain->name, $data['privacy']);

        $domain->update(['privacy' => $data['privacy']]);

        return back()->with('flash', ['success' => $data['privacy'] ? 'WHOIS privacy enabled.' : 'WHOIS privacy disabled.']);
    }

    public function refresh(Domain $domain)
    {
        try {
            $driver = DomainRegistrarService::driver($domain->registrar);
            $info = $driver->getInfo($domain->name);

            $domain->update([
                'expires_at' => $info['expires_at'] ?: $domain->expires_at,
                'locked' => $info['locked'],
                'privacy' => $info['privacy'],
                'nameserver_1' => $info['nameservers'][0] ?? null,
                'nameserver_2' => $info['nameservers'][1] ?? null,
                'nameserver_3' => $info['nameservers'][2] ?? null,
                'nameserver_4' => $info['nameservers'][3] ?? null,
            ]);

            return back()->with('flash', ['success' => 'Domain info refreshed from registrar.']);
        } catch (\Exception $e) {
            return back()->with('flash', ['error' => 'Refresh failed: '.$e->getMessage()]);
        }
    }
}
