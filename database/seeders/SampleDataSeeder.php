<?php

namespace Database\Seeders;

use App\Models\Affiliate;
use App\Models\Announcement;
use App\Models\CreditNote;
use App\Models\Department;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tax rate ───────────────────────────────────────────────────────────
        TaxRate::firstOrCreate(['name' => 'US Sales Tax (Sample)'], [
            'rate' => 8.00,
            'country' => 'US',
            'state' => '',
            'active' => true,
            'is_default' => false,
        ]);

        // ── Promo code ─────────────────────────────────────────────────────────
        PromoCode::firstOrCreate(['code' => 'BETA20'], [
            'type' => 'percent',
            'value' => 20.00,
            'max_uses' => 100,
            'uses_count' => 0,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
            'applies_once' => false,
        ]);

        // ── Products ───────────────────────────────────────────────────────────
        $starter = Product::firstOrCreate(['name' => 'Starter Hosting'], [
            'category' => 'Web Hosting',
            'short_description' => '1 website · 5 GB SSD · 50 GB bandwidth',
            'type' => 'shared',
            'price' => 4.99,
            'setup_fee' => 0.00,
            'billing_cycle' => 'monthly',
            'autosetup' => 'on_payment',
            'taxable' => true,
            'sort_order' => 1,
        ]);

        $business = Product::firstOrCreate(['name' => 'Business Hosting'], [
            'category' => 'Web Hosting',
            'short_description' => '5 websites · 25 GB SSD · unlimited bandwidth',
            'type' => 'shared',
            'price' => 9.99,
            'setup_fee' => 0.00,
            'billing_cycle' => 'monthly',
            'autosetup' => 'on_payment',
            'taxable' => true,
            'sort_order' => 2,
        ]);

        $pro = Product::firstOrCreate(['name' => 'Professional Hosting'], [
            'category' => 'Web Hosting',
            'short_description' => 'Unlimited websites · 100 GB NVMe · unlimited bandwidth',
            'type' => 'shared',
            'price' => 19.99,
            'setup_fee' => 5.00,
            'billing_cycle' => 'monthly',
            'autosetup' => 'on_payment',
            'taxable' => true,
            'sort_order' => 3,
        ]);

        $vps = Product::firstOrCreate(['name' => 'VPS Basic'], [
            'category' => 'VPS',
            'short_description' => '2 vCPU · 2 GB RAM · 40 GB NVMe · 2 TB bandwidth',
            'type' => 'vps',
            'price' => 29.99,
            'setup_fee' => 0.00,
            'billing_cycle' => 'monthly',
            'autosetup' => 'manual',
            'taxable' => true,
            'sort_order' => 4,
        ]);

        $domainProduct = Product::firstOrCreate(['name' => 'Domain Registration (.com)'], [
            'category' => 'Domains',
            'short_description' => '.com domain registration — 1 year',
            'type' => 'domain',
            'price' => 12.99,
            'setup_fee' => 0.00,
            'billing_cycle' => 'annual',
            'autosetup' => 'on_payment',
            'taxable' => false,
            'sort_order' => 10,
        ]);

        // ── Clients ────────────────────────────────────────────────────────────
        $clientRole = Role::findByName('client');

        $alice = $this->makeClient('Alice Johnson', 'alice@demo.strata', 'demo1234', 'US', 'CA', 'Demo Corp');
        $bob = $this->makeClient('Bob Smith', 'bob@demo.strata', 'demo1234', 'GB', '', 'Smith Digital');
        $carol = $this->makeClient('Carol White', 'carol@demo.strata', 'demo1234', 'CA', 'ON', '');
        $david = $this->makeClient('David Lee', 'david@demo.strata', 'demo1234', 'AU', '', 'Lee Solutions');
        $emma = $this->makeClient('Emma Brown', 'emma@demo.strata', 'demo1234', 'DE', '', '');

        // ── Announcement ───────────────────────────────────────────────────────
        Announcement::firstOrCreate(['title' => 'Welcome to Strata — Beta Testing'], [
            'body' => '<p>Welcome! This installation includes <strong>sample data</strong> to help you explore all features. All clients, invoices, and services shown are fictional and for demonstration purposes only.</p><p>Use the admin panel to explore billing, services, support tickets, quotes, and more. Default demo client password: <code>demo1234</code></p>',
            'published' => true,
            'published_at' => now(),
        ]);

        // ── Department ─────────────────────────────────────────────────────────
        $billing = Department::where('name', 'Billing')->first();
        $tech = Department::where('name', 'Technical Support')->first();
        $sales = Department::where('name', 'Sales')->first();
        $general = Department::where('name', 'General')->first();

        // ── Alice — active Business Hosting, paid invoices ─────────────────────
        $aliceService = Service::create([
            'user_id' => $alice->id,
            'product_id' => $business->id,
            'domain' => 'alicedemo.com',
            'status' => 'active',
            'amount' => 9.99,
            'billing_cycle' => 'monthly',
            'registration_date' => now()->subMonths(3)->toDateString(),
            'next_due_date' => now()->addDays(12)->toDateString(),
        ]);

        // Three months of paid invoices for Alice
        foreach ([3, 2, 1] as $monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $inv = Invoice::create([
                'user_id' => $alice->id,
                'status' => 'paid',
                'subtotal' => 9.99,
                'tax_rate' => 0,
                'tax' => 0.00,
                'total' => 9.99,
                'amount_due' => 0.00,
                'date' => $date->toDateString(),
                'due_date' => $date->addDays(7)->toDateString(),
                'paid_at' => $date->addDays(1),
            ]);
            InvoiceItem::create([
                'invoice_id' => $inv->id,
                'service_id' => $aliceService->id,
                'description' => 'Business Hosting — '.$date->format('F Y'),
                'quantity' => 1,
                'unit_price' => 9.99,
                'total' => 9.99,
            ]);
            Payment::create([
                'invoice_id' => $inv->id,
                'user_id' => $alice->id,
                'gateway' => 'stripe',
                'transaction_id' => 'ch_demo_'.strtolower(str_pad($inv->id, 8, '0', STR_PAD_LEFT)),
                'amount' => 9.99,
                'currency' => 'usd',
                'status' => 'completed',
                'paid_at' => $inv->paid_at,
            ]);
        }

        // Alice also has a Professional Hosting upgrade (domain: alicepro.com)
        $aliceProService = Service::create([
            'user_id' => $alice->id,
            'product_id' => $pro->id,
            'domain' => 'alicepro.com',
            'status' => 'active',
            'amount' => 19.99,
            'billing_cycle' => 'monthly',
            'registration_date' => now()->subMonth()->toDateString(),
            'next_due_date' => now()->addDays(18)->toDateString(),
        ]);

        $aliceProInv = Invoice::create([
            'user_id' => $alice->id,
            'status' => 'paid',
            'subtotal' => 24.99, // 19.99 + 5.00 setup
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 24.99,
            'amount_due' => 0.00,
            'date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subMonth()->addDays(7)->toDateString(),
            'paid_at' => now()->subMonth()->addDay(),
        ]);
        InvoiceItem::create(['invoice_id' => $aliceProInv->id, 'service_id' => $aliceProService->id, 'description' => 'Professional Hosting — Setup Fee', 'quantity' => 1, 'unit_price' => 5.00, 'total' => 5.00]);
        InvoiceItem::create(['invoice_id' => $aliceProInv->id, 'service_id' => $aliceProService->id, 'description' => 'Professional Hosting — '.now()->subMonth()->format('F Y'), 'quantity' => 1, 'unit_price' => 19.99, 'total' => 19.99]);
        Payment::create(['invoice_id' => $aliceProInv->id, 'user_id' => $alice->id, 'gateway' => 'stripe', 'transaction_id' => 'ch_demo_pro001', 'amount' => 24.99, 'currency' => 'usd', 'status' => 'completed', 'paid_at' => now()->subMonth()->addDay()]);

        // Alice domain
        Domain::create([
            'user_id' => $alice->id,
            'name' => 'alicedemo.com',
            'registrar' => 'namecheap',
            'status' => 'active',
            'registered_at' => now()->subMonths(3)->toDateString(),
            'expires_at' => now()->addMonths(9)->toDateString(),
            'auto_renew' => true,
            'locked' => true,
            'privacy' => false,
            'nameserver_1' => 'ns1.demo-host.com',
            'nameserver_2' => 'ns2.demo-host.com',
        ]);

        // ── Bob — active Starter Hosting, current unpaid invoice ──────────────
        $bobService = Service::create([
            'user_id' => $bob->id,
            'product_id' => $starter->id,
            'domain' => 'bobsmith.co.uk',
            'status' => 'active',
            'amount' => 4.99,
            'billing_cycle' => 'monthly',
            'registration_date' => now()->subMonths(2)->toDateString(),
            'next_due_date' => now()->addDays(5)->toDateString(),
        ]);

        // One paid, one unpaid (current)
        $bobPaid = Invoice::create([
            'user_id' => $bob->id,
            'status' => 'paid',
            'subtotal' => 4.99,
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 4.99,
            'amount_due' => 0.00,
            'date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->subMonths(2)->addDays(7)->toDateString(),
            'paid_at' => now()->subMonths(2)->addDays(2),
        ]);
        InvoiceItem::create(['invoice_id' => $bobPaid->id, 'service_id' => $bobService->id, 'description' => 'Starter Hosting — '.now()->subMonths(2)->format('F Y'), 'quantity' => 1, 'unit_price' => 4.99, 'total' => 4.99]);
        Payment::create(['invoice_id' => $bobPaid->id, 'user_id' => $bob->id, 'gateway' => 'paypal', 'transaction_id' => 'PAYPAL_DEMO_001', 'amount' => 4.99, 'currency' => 'usd', 'status' => 'completed', 'paid_at' => now()->subMonths(2)->addDays(2)]);

        $bobUnpaid = Invoice::create([
            'user_id' => $bob->id,
            'status' => 'unpaid',
            'subtotal' => 4.99,
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 4.99,
            'amount_due' => 4.99,
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
        ]);
        InvoiceItem::create(['invoice_id' => $bobUnpaid->id, 'service_id' => $bobService->id, 'description' => 'Starter Hosting — '.now()->format('F Y'), 'quantity' => 1, 'unit_price' => 4.99, 'total' => 4.99]);

        // ── Carol — pending service, overdue invoice ───────────────────────────
        $carolService = Service::create([
            'user_id' => $carol->id,
            'product_id' => $business->id,
            'domain' => 'carolwhite.ca',
            'status' => 'pending',
            'amount' => 9.99,
            'billing_cycle' => 'monthly',
            'registration_date' => now()->subDays(20)->toDateString(),
            'next_due_date' => now()->addDays(10)->toDateString(),
        ]);

        $carolOverdue = Invoice::create([
            'user_id' => $carol->id,
            'status' => 'unpaid',
            'subtotal' => 9.99,
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 9.99,
            'amount_due' => 9.99,
            'date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(6)->toDateString(), // Overdue
        ]);
        InvoiceItem::create(['invoice_id' => $carolOverdue->id, 'service_id' => $carolService->id, 'description' => 'Business Hosting — Initial Invoice', 'quantity' => 1, 'unit_price' => 9.99, 'total' => 9.99]);

        // ── David — VPS, credit note on account ───────────────────────────────
        $davidService = Service::create([
            'user_id' => $david->id,
            'product_id' => $vps->id,
            'domain' => '',
            'status' => 'active',
            'amount' => 29.99,
            'billing_cycle' => 'monthly',
            'registration_date' => now()->subMonths(4)->toDateString(),
            'next_due_date' => now()->addDays(22)->toDateString(),
        ]);

        $davidPaid1 = Invoice::create([
            'user_id' => $david->id,
            'status' => 'paid',
            'subtotal' => 29.99,
            'tax_rate' => 8.00,
            'tax' => 2.40,
            'total' => 32.39,
            'amount_due' => 0.00,
            'date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->subMonths(2)->addDays(7)->toDateString(),
            'paid_at' => now()->subMonths(2)->addDays(1),
        ]);
        InvoiceItem::create(['invoice_id' => $davidPaid1->id, 'service_id' => $davidService->id, 'description' => 'VPS Basic — '.now()->subMonths(2)->format('F Y'), 'quantity' => 1, 'unit_price' => 29.99, 'total' => 29.99]);
        Payment::create(['invoice_id' => $davidPaid1->id, 'user_id' => $david->id, 'gateway' => 'stripe', 'transaction_id' => 'ch_demo_vps001', 'amount' => 32.39, 'currency' => 'usd', 'status' => 'completed', 'paid_at' => now()->subMonths(2)->addDays(1)]);

        // David has a credit note applied to last invoice (billing dispute)
        $davidPartialInv = Invoice::create([
            'user_id' => $david->id,
            'status' => 'paid',
            'subtotal' => 29.99,
            'tax_rate' => 8.00,
            'tax' => 2.40,
            'total' => 32.39,
            'credit_applied' => 10.00,
            'amount_due' => 0.00,
            'date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subMonth()->addDays(7)->toDateString(),
            'paid_at' => now()->subMonth()->addDays(3),
        ]);
        InvoiceItem::create(['invoice_id' => $davidPartialInv->id, 'service_id' => $davidService->id, 'description' => 'VPS Basic — '.now()->subMonth()->format('F Y'), 'quantity' => 1, 'unit_price' => 29.99, 'total' => 29.99]);
        Payment::create(['invoice_id' => $davidPartialInv->id, 'user_id' => $david->id, 'gateway' => 'stripe', 'transaction_id' => 'ch_demo_vps002', 'amount' => 22.39, 'currency' => 'usd', 'status' => 'completed', 'paid_at' => now()->subMonth()->addDays(3)]);

        $davidCN = CreditNote::create([
            'invoice_id' => $davidPartialInv->id,
            'user_id' => $david->id,
            'credit_note_number' => 'CN-SAMPLE-0001',
            'amount' => 10.00,
            'reason' => 'Service downtime — 48 hour SLA credit',
            'status' => 'applied',
            'disposition' => 'invoice',
            'issued_at' => now()->subMonth()->addDay(),
        ]);

        // David domain
        Domain::create([
            'user_id' => $david->id,
            'name' => 'davidlee-vps.com.au',
            'registrar' => 'namecheap',
            'status' => 'active',
            'registered_at' => now()->subMonths(4)->toDateString(),
            'expires_at' => now()->addMonths(8)->toDateString(),
            'auto_renew' => true,
            'locked' => false,
            'privacy' => true,
            'nameserver_1' => 'ns1.demo-host.com',
            'nameserver_2' => 'ns2.demo-host.com',
        ]);

        // ── Emma — new client, quote pending ──────────────────────────────────
        // No services yet — shows the "empty state" experience

        // ── Quotes ─────────────────────────────────────────────────────────────
        // Pending quote for Emma
        $emmaQuote = Quote::create([
            'user_id' => $emma->id,
            'quote_number' => 'QT-DEMO-0001',
            'status' => 'sent',
            'subtotal' => 59.97,
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 59.97,
            'valid_until' => now()->addDays(30)->toDateString(),
            'client_message' => 'Please find attached our proposed hosting package for your new e-commerce site.',
        ]);
        QuoteItem::create(['quote_id' => $emmaQuote->id, 'description' => 'Professional Hosting — 3 months prepay', 'quantity' => 3, 'unit_price' => 19.99, 'total' => 59.97]);

        // Accepted quote for Alice (converted)
        $aliceQuote = Quote::create([
            'user_id' => $alice->id,
            'quote_number' => 'QT-DEMO-0002',
            'status' => 'accepted',
            'subtotal' => 9.99,
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 9.99,
            'valid_until' => now()->subDays(5)->toDateString(),
            'client_message' => 'Upgrade quote as discussed.',
            'converted_invoice_id' => $aliceProInv->id,
        ]);
        QuoteItem::create(['quote_id' => $aliceQuote->id, 'description' => 'Business Hosting — upgrade to Professional', 'quantity' => 1, 'unit_price' => 9.99, 'total' => 9.99]);

        // Declined quote for Bob
        $bobQuote = Quote::create([
            'user_id' => $bob->id,
            'quote_number' => 'QT-DEMO-0003',
            'status' => 'declined',
            'subtotal' => 29.99,
            'tax_rate' => 0,
            'tax' => 0.00,
            'total' => 29.99,
            'valid_until' => now()->subDays(15)->toDateString(),
        ]);
        QuoteItem::create(['quote_id' => $bobQuote->id, 'description' => 'VPS Basic — upgrade from Starter Hosting', 'quantity' => 1, 'unit_price' => 29.99, 'total' => 29.99]);

        // ── Support tickets ────────────────────────────────────────────────────
        if ($tech && $billing && $general) {

            // Open ticket — Alice, technical issue
            $t1 = SupportTicket::create([
                'user_id' => $alice->id,
                'department_id' => $tech->id,
                'department' => 'Technical Support',
                'subject' => 'Unable to access cPanel for alicepro.com',
                'status' => 'open',
                'priority' => 'high',
                'last_reply_at' => now()->subHours(3),
            ]);
            SupportReply::create(['ticket_id' => $t1->id, 'user_id' => $alice->id, 'message' => "Hi, I'm trying to log into cPanel for my new Professional Hosting account (alicepro.com) but it says my password is incorrect. I've tried resetting it twice with no luck. Can you help?", 'is_staff' => false, 'internal' => false]);

            // Answered ticket — Bob, billing question
            $t2 = SupportTicket::create([
                'user_id' => $bob->id,
                'department_id' => $billing->id,
                'department' => 'Billing',
                'subject' => 'Invoice payment question',
                'status' => 'answered',
                'priority' => 'medium',
                'last_reply_at' => now()->subDay(),
                'first_replied_at' => now()->subHours(22),
            ]);
            SupportReply::create(['ticket_id' => $t2->id, 'user_id' => $bob->id, 'message' => "Hello, I have an outstanding invoice but I can't see a PayPal option anymore. Is that still available?", 'is_staff' => false, 'internal' => false]);
            SupportReply::create(['ticket_id' => $t2->id, 'user_id' => User::role('super-admin')->first()?->id ?? $alice->id, 'message' => 'Hi Bob, PayPal is available on the invoice payment page. Please navigate to Invoices, open invoice #'.$bobUnpaid->id.', and you will see the PayPal button. Let us know if you have any trouble!', 'is_staff' => true, 'internal' => false]);

            // Closed ticket — Carol, resolved setup issue
            $t3 = SupportTicket::create([
                'user_id' => $carol->id,
                'department_id' => $tech->id,
                'department' => 'Technical Support',
                'subject' => 'New hosting account not set up yet',
                'status' => 'closed',
                'priority' => 'medium',
                'last_reply_at' => now()->subDays(5),
                'first_replied_at' => now()->subDays(8),
                'closed_at' => now()->subDays(5),
            ]);
            SupportReply::create(['ticket_id' => $t3->id, 'user_id' => $carol->id, 'message' => 'I signed up 2 days ago and my hosting account still shows as Pending. When will it be activated?', 'is_staff' => false, 'internal' => false]);
            SupportReply::create(['ticket_id' => $t3->id, 'user_id' => User::role('super-admin')->first()?->id ?? $alice->id, 'message' => "Hi Carol, your invoice is still showing as unpaid — once that's settled your account will be activated automatically. Please check your invoice and let us know if you need assistance with payment.", 'is_staff' => true, 'internal' => false]);

            // Open ticket — David, general enquiry
            $t4 = SupportTicket::create([
                'user_id' => $david->id,
                'department_id' => $general->id,
                'department' => 'General',
                'subject' => 'Can I add additional IPs to my VPS?',
                'status' => 'open',
                'priority' => 'low',
                'last_reply_at' => now()->subHours(1),
            ]);
            SupportReply::create(['ticket_id' => $t4->id, 'user_id' => $david->id, 'message' => 'Hi, I need an additional IP address for my VPS to host a separate SSL certificate. Is this something I can order, and if so, what are the costs?', 'is_staff' => false, 'internal' => false]);
        }

        // ── Affiliate ──────────────────────────────────────────────────────────
        Affiliate::firstOrCreate(['user_id' => $alice->id], [
            'code' => 'ALICE2024',
            'status' => 'active',
            'commission_type' => 'percent',
            'commission_value' => 10.00,
            'balance' => 4.99,
            'total_earned' => 4.99,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function makeClient(string $name, string $email, string $password, string $country, string $state, string $company): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'country' => $country,
            'state' => $state,
            'company' => $company,
        ]);

        if (! $user->hasRole('client')) {
            $user->assignRole('client');
        }

        return $user;
    }
}
