<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        AuditLogger::log('settings.updated', null, array_keys($data));

        return back()->with('flash', ['success' => 'Settings saved.']);
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer'        => ['required', 'in:sendmail,smtp,log'],
            'mail_from_address'  => ['required', 'email', 'max:255'],
            'mail_from_name'     => ['required', 'string', 'max:255'],
            'mail_host'          => ['nullable', 'string', 'max:255'],
            'mail_port'          => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username'      => ['nullable', 'string', 'max:255'],
            'mail_password'      => ['nullable', 'string', 'max:255'],
            'mail_encryption'    => ['nullable', 'in:tls,ssl,'],
            'mail_sendmail_path' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::setMany($data);

        AuditLogger::log('settings.mail_updated', null, ['mail_mailer', 'mail_from_address']);

        return back()->with('flash', ['success' => 'Mail settings saved.']);
    }

    public function testMail(Request $request): JsonResponse
    {
        $request->validate([
            'to' => ['required', 'email'],
        ]);

        set_time_limit(15);

        $mailer  = Setting::get('mail_mailer', config('mail.default'));
        $from    = Setting::get('mail_from_address', config('mail.from.address'));
        $to      = $request->input('to');

        // For sendmail: bypass Laravel's mail system and call the binary directly
        // so we can detect hangs and return a clean error.
        if ($mailer === 'sendmail') {
            $path = Setting::get('mail_sendmail_path', '/usr/sbin/sendmail -t -i');
            $bin  = explode(' ', $path)[0];

            if (! file_exists($bin) || ! is_executable($bin)) {
                return response()->json(['success' => false, 'message' => "sendmail binary not found or not executable: {$bin}"], 422);
            }

            $message  = "To: {$to}\r\nFrom: {$from}\r\nSubject: Strata - Mail Test\r\n\r\n";
            $message .= "This is a test email from Strata. Your mail configuration is working.\r\n";

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($path, $descriptors, $pipes);

            if (! is_resource($proc)) {
                return response()->json(['success' => false, 'message' => 'Failed to open sendmail process.'], 422);
            }

            fwrite($pipes[0], $message);
            fclose($pipes[0]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($proc);

            if ($exit !== 0) {
                return response()->json(['success' => false, 'message' => "sendmail exited with code {$exit}: {$stderr}"], 422);
            }

            return response()->json(['success' => true, 'message' => 'Test email sent via sendmail.']);
        }

        try {
            Mail::raw('This is a test email from Strata. Your mail configuration is working.', function ($msg) use ($to) {
                $msg->to($to)->subject('Strata — Mail Test');
            });

            return response()->json(['success' => true, 'message' => 'Test email sent.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        // Delete old logo if present
        $old = Setting::get('logo_path');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('logo')->store('logos', 'public');

        Setting::set('logo_path', $path);

        return back()->with('flash', ['success' => 'Logo uploaded.']);
    }
}
