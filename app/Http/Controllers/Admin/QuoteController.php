<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TemplateMailable;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Quotes/Index', [
            'quotes' => Quote::with('user')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Quotes/Form', [
            'clients' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'valid_until' => ['nullable', 'date'],
            'client_message' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $quote = DB::transaction(function () use ($data, $request) {
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
            $taxRate = (float) ($data['tax_rate'] ?? 0);
            $tax = round($subtotal * ($taxRate / 100), 2);

            $q = Quote::create([
                'user_id' => $data['user_id'],
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'valid_until' => $data['valid_until'] ?? null,
                'client_message' => $data['client_message'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $q->update([
                'quote_number' => 'QUO-'.now()->format('Ymd').'-'.str_pad($q->id, 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($data['items'] as $item) {
                $q->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }

            AuditLogger::log('quote.created', $q);

            return $q;
        });

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', "Quote {$quote->quote_number} created.");
    }

    public function show(Quote $quote): Response
    {
        $quote->load(['user', 'items']);

        return Inertia::render('Admin/Quotes/Show', ['quote' => $quote]);
    }

    public function edit(Quote $quote): Response
    {
        $quote->load('items');

        return Inertia::render('Admin/Quotes/Form', [
            'quote' => $quote,
            'clients' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        abort_if(in_array($quote->status, ['accepted', 'declined']), 422);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'valid_until' => ['nullable', 'date'],
            'client_message' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($quote, $data) {
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
            $taxRate = (float) ($data['tax_rate'] ?? 0);
            $tax = round($subtotal * ($taxRate / 100), 2);

            $quote->update([
                'user_id' => $data['user_id'],
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'valid_until' => $data['valid_until'] ?? null,
                'client_message' => $data['client_message'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $quote->items()->delete();

            foreach ($data['items'] as $item) {
                $quote->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }
        });

        return redirect()->route('admin.quotes.show', $quote)
            ->with('success', 'Quote updated.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        abort_if($quote->status === 'accepted', 422);

        $quote->delete();

        return redirect()->route('admin.quotes.index')
            ->with('success', 'Quote deleted.');
    }

    /** Send quote to client — transitions status to "sent". */
    public function send(Quote $quote): RedirectResponse
    {
        abort_if(in_array($quote->status, ['accepted', 'declined']), 422);

        $quote->load('user');
        $quote->update(['status' => 'sent']);

        try {
            Mail::to($quote->user->email)->send(new TemplateMailable('quote.sent', [
                'name' => $quote->user->name,
                'app_name' => config('app.name'),
                'quote_number' => $quote->quote_number,
                'total' => number_format((float) $quote->total, 2),
                'valid_until' => $quote->valid_until?->format('M d, Y') ?? 'Open',
                'quote_url' => route('client.quotes.show', $quote->id),
                'message' => $quote->client_message ?? '',
            ]));
        } catch (\Throwable) {
            // Mail failure must not block the status change
        }

        AuditLogger::log('quote.sent', $quote);

        return back()->with('success', 'Quote sent to client.');
    }

    /** Convert an accepted quote into an invoice. */
    public function convert(Quote $quote): RedirectResponse
    {
        abort_unless($quote->status === 'accepted', 422);
        abort_if($quote->converted_invoice_id, 422);

        $quote->load('items');

        $invoice = DB::transaction(function () use ($quote) {
            $inv = Invoice::create([
                'user_id' => $quote->user_id,
                'status' => 'unpaid',
                'subtotal' => $quote->subtotal,
                'tax_rate' => $quote->tax_rate,
                'tax' => $quote->tax,
                'total' => $quote->total,
                'amount_due' => $quote->total,
                'date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => "Converted from quote {$quote->quote_number}",
            ]);

            foreach ($quote->items as $item) {
                $inv->items()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                ]);
            }

            $quote->update(['converted_invoice_id' => $inv->id]);

            AuditLogger::log('quote.converted', $quote, ['invoice_id' => $inv->id]);

            return $inv;
        });

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', "Invoice #{$invoice->id} created from {$quote->quote_number}.");
    }
}
