<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => Setting::allKeyed(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name'      => ['nullable', 'string', 'max:255'],
            'timezone'          => ['nullable', 'string', 'max:100'],
            'date_format'       => ['nullable', 'string', 'max:50'],
            'company_email'     => ['nullable', 'email', 'max:255'],
            'company_phone'     => ['nullable', 'string', 'max:50'],
            'company_address'   => ['nullable', 'string', 'max:255'],
            'company_city'      => ['nullable', 'string', 'max:100'],
            'company_state'     => ['nullable', 'string', 'max:100'],
            'company_zip'       => ['nullable', 'string', 'max:20'],
            'company_country'   => ['nullable', 'string', 'max:100'],
            'currency'          => ['nullable', 'string', 'max:10'],
            'currency_symbol'   => ['nullable', 'string', 'max:5'],
            'invoice_prefix'    => ['nullable', 'string', 'max:20'],
            'invoice_due_days'  => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tax_rate'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_name'          => ['nullable', 'string', 'max:50'],
        ]);

        Setting::setMany($data);

        return back()->with('flash', ['success' => 'Settings saved.']);
    }
}
