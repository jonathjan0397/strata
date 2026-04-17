<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\StrataLicense;
use App\Services\SystemBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => Setting::allKeyed(),
            'appUrl' => rtrim(config('app.url'), '/'),
            'backups' => SystemBackup::listBackups(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_title' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'date_format' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_city' => ['nullable', 'string', 'max:100'],
            'company_state' => ['nullable', 'string', 'max:100'],
            'company_zip' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:5'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_name' => ['nullable', 'string', 'max:50'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'portal_theme' => ['nullable', 'in:blue,red,green,lightblue'],
            'domain_search_tlds' => ['nullable', 'string', 'max:500'],
            'otp_enabled' => ['nullable', 'boolean'],
            'otp_lifetime' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'otp_keep_alive' => ['nullable', 'boolean'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:2000'],
            'affiliate_default_commission_type' => ['nullable', 'in:percent,fixed'],
            'affiliate_default_commission_value' => ['nullable', 'numeric', 'min:0'],
            'affiliate_default_payout_threshold' => ['nullable', 'numeric', 'min:0'],
            'brand_primary_color' => ['nullable', 'string', 'max:20', 'regex:/^(#[0-9A-Fa-f]{3,8})?$/'],
            'brand_accent_color' => ['nullable', 'string', 'max:20', 'regex:/^(#[0-9A-Fa-f]{3,8})?$/'],
            'portal_hero_badge' => ['nullable', 'string', 'max:100'],
            'portal_hero_title' => ['nullable', 'string', 'max:100'],
            'portal_footer_links' => ['nullable', 'string', 'max:3000'],
            'portal_feature_cards' => ['nullable', 'string', 'max:5000'],
            'portal_stat_items' => ['nullable', 'string', 'max:1000'],
            'portal_custom_css' => ['nullable', 'string', 'max:10000'],
        ]);

        Setting::setMany($data);

        AuditLogger::log('settings.updated', null, array_keys($data));

        return back()->with('flash', ['success' => 'Settings saved.']);
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['required', 'in:sendmail,smtp,log'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:auto,tls,ssl,'],
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

        $mailer = Setting::get('mail_mailer', config('mail.default'));
        $from = Setting::get('mail_from_address', config('mail.from.address'));
        $to = $request->input('to');

        if ($mailer === 'sendmail') {
            $path = Setting::get('mail_sendmail_path', '/usr/sbin/sendmail -t -i');
            $bin = explode(' ', $path)[0];

            if (! file_exists($bin) || ! is_executable($bin)) {
                return response()->json(['success' => false, 'message' => "sendmail binary not found or not executable: {$bin}"], 422);
            }

            $siteName = Setting::get('site_title', Setting::get('company_name', config('app.name')));
            $message = "To: {$to}\r\nFrom: {$from}\r\nSubject: {$siteName} - Mail Test\r\n\r\n";
            $message .= "This is a test email from {$siteName}. Your mail configuration is working.\r\n";

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
            $siteName = Setting::get('site_title', Setting::get('company_name', config('app.name')));
            Mail::raw("This is a test email from {$siteName}. Your mail configuration is working.", function ($msg) use ($to, $siteName) {
                $msg->to($to)->subject("{$siteName} - Mail Test");
            });

            return response()->json(['success' => true, 'message' => 'Test email sent.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateIntegrations(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'integration_google_client_id' => ['nullable', 'string', 'max:255'],
            'integration_google_client_secret' => ['nullable', 'string', 'max:500'],
            'integration_microsoft_client_id' => ['nullable', 'string', 'max:255'],
            'integration_microsoft_client_secret' => ['nullable', 'string', 'max:500'],
            'integration_microsoft_tenant' => ['nullable', 'string', 'max:100'],
            'integration_stripe_key' => ['nullable', 'string', 'max:255'],
            'integration_stripe_secret' => ['nullable', 'string', 'max:255'],
            'integration_stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'integration_paypal_client_id' => ['nullable', 'string', 'max:255'],
            'integration_paypal_client_secret' => ['nullable', 'string', 'max:255'],
            'integration_paypal_mode' => ['nullable', 'in:sandbox,live'],
            'integration_authorizenet_login_id' => ['nullable', 'string', 'max:100'],
            'integration_authorizenet_transaction_key' => ['nullable', 'string', 'max:100'],
            'integration_authorizenet_client_key' => ['nullable', 'string', 'max:255'],
            'integration_authorizenet_sandbox' => ['nullable', 'boolean'],
            'fraud_check_enabled' => ['nullable', 'boolean'],
            'fraud_maxmind_account_id' => ['nullable', 'string', 'max:20'],
            'fraud_maxmind_license_key' => ['nullable', 'string', 'max:255'],
            'fraud_score_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fraud_action' => ['nullable', 'in:flag,reject'],
            'integration_registrar_driver' => ['nullable', 'in:namecheap,enom,opensrs,hexonet'],
            'integration_namecheap_api_user' => ['nullable', 'string', 'max:100'],
            'integration_namecheap_api_key' => ['nullable', 'string', 'max:255'],
            'integration_namecheap_client_ip' => ['nullable', 'ip'],
            'integration_namecheap_sandbox' => ['nullable', 'boolean'],
            'integration_enom_uid' => ['nullable', 'string', 'max:100'],
            'integration_enom_pw' => ['nullable', 'string', 'max:255'],
            'integration_enom_sandbox' => ['nullable', 'boolean'],
            'integration_opensrs_api_key' => ['nullable', 'string', 'max:255'],
            'integration_opensrs_reseller_username' => ['nullable', 'string', 'max:100'],
            'integration_opensrs_sandbox' => ['nullable', 'boolean'],
            'integration_hexonet_login' => ['nullable', 'string', 'max:100'],
            'integration_hexonet_password' => ['nullable', 'string', 'max:255'],
            'integration_hexonet_sandbox' => ['nullable', 'boolean'],
            'integration_namecheap_offer_privacy' => ['nullable', 'boolean'],
            'integration_namecheap_default_privacy' => ['nullable', 'boolean'],
            'integration_namecheap_default_lock' => ['nullable', 'boolean'],
            'integration_enom_offer_privacy' => ['nullable', 'boolean'],
            'integration_enom_default_lock' => ['nullable', 'boolean'],
            'integration_opensrs_offer_privacy' => ['nullable', 'boolean'],
            'integration_opensrs_default_privacy' => ['nullable', 'boolean'],
            'integration_opensrs_default_lock' => ['nullable', 'boolean'],
            'integration_hexonet_offer_privacy' => ['nullable', 'boolean'],
            'integration_hexonet_default_privacy' => ['nullable', 'boolean'],
            'integration_hexonet_default_lock' => ['nullable', 'boolean'],
        ]);

        Setting::setMany($data);

        AuditLogger::log('settings.integrations_updated');

        return back()->with('flash', ['success' => 'Integration settings saved.']);
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        $old = Setting::get('logo_path');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('logo')->store('logos', 'public');

        Setting::set('logo_path', $path);

        return back()->with('flash', ['success' => 'Logo uploaded.']);
    }

    public function uploadFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:512'],
        ]);

        $old = Setting::get('favicon_path');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('favicon')->store('favicons', 'public');
        Setting::set('favicon_path', $path);

        return back()->with('flash', ['success' => 'Favicon uploaded.']);
    }

    public function syncLicense(): RedirectResponse
    {
        $result = StrataLicense::sync();

        $status = $result['status'] ?? 'unknown';
        $features = implode(', ', StrataLicense::features()) ?: 'none';
        $messages = count($result['messages'] ?? []);

        AuditLogger::log('settings.license_synced', null, ['status' => $status]);

        return back()->with('flash', [
            'success' => "License synced - status: {$status}, features: {$features}, messages: {$messages}.",
        ]);
    }

    public function downloadBackup(): BinaryFileResponse
    {
        $backup = SystemBackup::createBackup();

        AuditLogger::log('settings.backup_created', null, [
            'filename' => $backup['filename'],
        ]);

        return response()->download($backup['path'], $backup['filename']);
    }

    public function downloadBackupFile(string $filename): BinaryFileResponse
    {
        $safeFilename = basename($filename);
        $path = storage_path('app/backups/'.$safeFilename);

        abort_unless(is_file($path), 404);

        return response()->download($path, $safeFilename);
    }

    public function restoreBackup(Request $request): RedirectResponse
    {
        $request->validate([
            'archive' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), (string) Auth::user()?->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your current password is required to restore a backup.',
            ]);
        }

        if (trim((string) $request->input('confirmation')) !== 'RESTORE BILLING DATA') {
            throw ValidationException::withMessages([
                'confirmation' => 'Type RESTORE BILLING DATA to confirm this destructive restore.',
            ]);
        }

        SystemBackup::restoreFromUpload($request->file('archive'));

        AuditLogger::log('settings.backup_restored', null, [
            'filename' => $request->file('archive')->getClientOriginalName(),
        ]);

        return back()->with('flash', [
            'success' => 'Backup restored. The billing database, customer files, and stored settings were replaced from the archive.',
        ]);
    }

    public function emailDeliverability(): JsonResponse
    {
        $fromAddress = Setting::get('mail_from_address', config('mail.from.address'));

        if (! $fromAddress || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'No valid From address is configured in Settings -> Email.'], 422);
        }

        $sendingDomain = strtolower(substr(strrchr($fromAddress, '@'), 1));
        $parts = explode('.', $sendingDomain);
        $orgDomain = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $sendingDomain;
        $isSubdomain = $sendingDomain !== $orgDomain;

        $serverIp = request()->server('SERVER_ADDR', '');
        if (! $serverIp || $serverIp === '::1' || $serverIp === '127.0.0.1') {
            $serverIp = @gethostbyname((string) gethostname()) ?: 'YOUR_SERVER_IP';
        }

        $spfFound = false;
        $spfRecord = null;
        $records = @dns_get_record($sendingDomain, DNS_TXT) ?: [];
        foreach ($records as $r) {
            if (isset($r['txt']) && str_starts_with($r['txt'], 'v=spf1')) {
                $spfFound = true;
                $spfRecord = $r['txt'];
                break;
            }
        }

        $dkimFound = false;
        $dkimSelector = null;
        $dkimRecord = null;
        foreach (['default', 'mail', 'smtp', 's1', 'k1', 'google', 'selector1', 'selector2'] as $sel) {
            $dkimRecords = @dns_get_record("{$sel}._domainkey.{$sendingDomain}", DNS_TXT) ?: [];
            foreach ($dkimRecords as $r) {
                if (isset($r['txt']) && str_contains($r['txt'], 'v=DKIM1')) {
                    $dkimFound = true;
                    $dkimSelector = $sel;
                    $dkimRecord = strlen($r['txt']) > 80 ? substr($r['txt'], 0, 80).'...' : $r['txt'];
                    break 2;
                }
            }
        }

        $dmarcFound = false;
        $dmarcRecord = null;
        $dmarcHost = '_dmarc.'.$orgDomain;
        $dmarcRecords = @dns_get_record($dmarcHost, DNS_TXT) ?: [];
        foreach ($dmarcRecords as $r) {
            if (isset($r['txt']) && str_starts_with($r['txt'], 'v=DMARC1')) {
                $dmarcFound = true;
                $dmarcRecord = $r['txt'];
                break;
            }
        }

        return response()->json([
            'from_address' => $fromAddress,
            'sending_domain' => $sendingDomain,
            'org_domain' => $orgDomain,
            'is_subdomain' => $isSubdomain,
            'server_ip' => $serverIp,
            'spf' => [
                'status' => $spfFound ? 'pass' : 'missing',
                'record' => $spfRecord,
                'host' => $sendingDomain,
                'suggested' => "v=spf1 ip4:{$serverIp} ~all",
            ],
            'dkim' => [
                'status' => $dkimFound ? 'pass' : 'missing',
                'selector' => $dkimSelector,
                'record' => $dkimRecord,
                'host' => "mail._domainkey.{$sendingDomain}",
            ],
            'dmarc' => [
                'status' => $dmarcFound ? 'pass' : 'missing',
                'record' => $dmarcRecord,
                'host' => $dmarcHost,
                'suggested' => "v=DMARC1; p=quarantine; rua=mailto:postmaster@{$orgDomain}",
            ],
        ]);
    }
}
