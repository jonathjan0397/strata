<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1f2937; background: #fff; }
  .page { padding: 48px; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
  .brand { font-size: 22px; font-weight: 700; color: #4f46e5; letter-spacing: -0.5px; }
  .brand-sub { font-size: 11px; color: #9ca3af; margin-top: 2px; }
  .invoice-meta { text-align: right; }
  .invoice-meta h1 { font-size: 20px; font-weight: 700; color: #111827; }
  .invoice-meta .badge {
    display: inline-block; margin-top: 4px; padding: 2px 10px;
    border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  }
  .badge-paid     { background: #dcfce7; color: #166534; }
  .badge-unpaid   { background: #fef9c3; color: #854d0e; }
  .badge-overdue  { background: #fee2e2; color: #991b1b; }
  .badge-cancelled{ background: #f3f4f6; color: #6b7280; }

  /* Parties */
  .parties { display: flex; justify-content: space-between; margin-bottom: 32px; gap: 24px; }
  .party { flex: 1; }
  .party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 6px; }
  .party-name { font-weight: 600; color: #111827; margin-bottom: 2px; }
  .party-detail { color: #6b7280; font-size: 12px; line-height: 1.5; }

  /* Dates row */
  .dates { display: flex; gap: 40px; margin-bottom: 32px; padding: 14px 0; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
  .date-item label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 2px; }
  .date-item span { font-size: 13px; color: #111827; }

  /* Line items table */
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  thead tr { background: #f9fafb; }
  th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
  th.right { text-align: right; }
  td { padding: 11px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; }
  td.right { text-align: right; }
  td.desc { color: #111827; }

  /* Totals */
  .totals { margin-left: auto; width: 260px; }
  .totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; color: #6b7280; }
  .totals-row.grand { border-top: 2px solid #e5e7eb; margin-top: 6px; padding-top: 10px; font-size: 16px; font-weight: 700; color: #111827; }
  .totals-row.due { font-size: 15px; font-weight: 700; color: #4f46e5; padding-top: 4px; }
  .totals-row .credit { color: #16a34a; }

  /* Payments */
  .payments { margin-top: 32px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
  .payments h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 10px; }
  .payment-row { display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; padding: 4px 0; }

  /* Footer */
  .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #9ca3af; }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  @php
    $companyName    = setting('company_name', config('app.name'));
    $companyAddress = setting('company_address', '');
    $currencySymbol = setting('currency_symbol', '$');
    $logoPath       = setting('logo_path');
  @endphp
  <div class="header">
    <div>
      @if($logoPath)
        <img src="{{ storage_path('app/public/' . $logoPath) }}" alt="{{ $companyName }}" style="max-height:48px;max-width:180px;margin-bottom:4px;display:block;">
      @else
        <div class="brand">{{ $companyName }}</div>
      @endif
      <div class="brand-sub">{{ config('app.url') }}</div>
    </div>
    <div class="invoice-meta">
      <h1>Invoice #{{ $invoice->id }}</h1>
      @php
        $badgeClass = match($invoice->status) {
          'paid'      => 'badge-paid',
          'unpaid'    => 'badge-unpaid',
          'overdue'   => 'badge-overdue',
          'cancelled' => 'badge-cancelled',
          default     => 'badge-unpaid',
        };
      @endphp
      <span class="badge {{ $badgeClass }}">{{ ucfirst($invoice->status) }}</span>
    </div>
  </div>

  <!-- Parties -->
  <div class="parties">
    <div class="party">
      <div class="party-label">Bill To</div>
      <div class="party-name">{{ $invoice->user->name }}</div>
      <div class="party-detail">{{ $invoice->user->email }}</div>
    </div>
    <div class="party" style="text-align:right">
      <div class="party-label">From</div>
      <div class="party-name">{{ $companyName }}</div>
      @if($companyAddress)
        <div class="party-detail" style="white-space:pre-line;">{{ $companyAddress }}</div>
      @endif
    </div>
  </div>

  <!-- Dates -->
  <div class="dates">
    <div class="date-item">
      <label>Invoice Date</label>
      <span>{{ \Carbon\Carbon::parse($invoice->date)->format('M d, Y') }}</span>
    </div>
    <div class="date-item">
      <label>Due Date</label>
      <span>{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</span>
    </div>
    @if($invoice->paid_at)
    <div class="date-item">
      <label>Paid On</label>
      <span>{{ \Carbon\Carbon::parse($invoice->paid_at)->format('M d, Y') }}</span>
    </div>
    @endif
  </div>

  <!-- Line items -->
  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="right" style="width:60px">Qty</th>
        <th class="right" style="width:90px">Unit Price</th>
        <th class="right" style="width:90px">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->items as $item)
      <tr>
        <td class="desc">{{ $item->description }}</td>
        <td class="right">{{ $item->quantity }}</td>
        <td class="right">{{ $currencySymbol }}{{ number_format($item->unit_price, 2) }}</td>
        <td class="right">{{ $currencySymbol }}{{ number_format($item->total, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <!-- Totals -->
  <div class="totals">
    @if($invoice->tax > 0)
    <div class="totals-row">
      <span>Subtotal</span>
      <span>{{ $currencySymbol }}{{ number_format($invoice->subtotal, 2) }}</span>
    </div>
    <div class="totals-row">
      <span>Tax ({{ $invoice->tax_rate }}%)</span>
      <span>{{ $currencySymbol }}{{ number_format($invoice->tax, 2) }}</span>
    </div>
    @endif
    <div class="totals-row grand">
      <span>Total</span>
      <span>{{ $currencySymbol }}{{ number_format($invoice->total, 2) }}</span>
    </div>
    @if($invoice->credit_applied > 0)
    <div class="totals-row">
      <span>Credit Applied</span>
      <span class="credit">-{{ $currencySymbol }}{{ number_format($invoice->credit_applied, 2) }}</span>
    </div>
    <div class="totals-row due">
      <span>Amount Due</span>
      <span>{{ $currencySymbol }}{{ number_format($invoice->amount_due, 2) }}</span>
    </div>
    @endif
  </div>

  <!-- Payment history -->
  @if($invoice->payments->where('status','completed')->count())
  <div class="payments">
    <h3>Payments Received</h3>
    @foreach($invoice->payments->where('status','completed') as $payment)
    <div class="payment-row">
      <span>{{ ucfirst($payment->gateway) }} — {{ $payment->transaction_id }}</span>
      <span>{{ $currencySymbol }}{{ number_format($payment->amount, 2) }} on {{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') }}</span>
    </div>
    @endforeach
  </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    Thank you for your business. Questions? Contact us at {{ config('mail.from.address') }}.
  </div>

</div>
</body>
</html>
