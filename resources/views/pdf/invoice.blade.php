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
  .brand { font-size: 22px; font-weight: 700; color: #4f46e5; letter-spacing: -0.5px; }
  .brand-sub { font-size: 11px; color: #9ca3af; margin-top: 2px; }
  .invoice-meta-title { font-size: 20px; font-weight: 700; color: #111827; }
  .badge {
    display: inline-block; margin-top: 4px; padding: 2px 10px;
    border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  }
  .badge-paid     { background: #dcfce7; color: #166534; }
  .badge-unpaid   { background: #fef9c3; color: #854d0e; }
  .badge-overdue  { background: #fee2e2; color: #991b1b; }
  .badge-cancelled{ background: #f3f4f6; color: #6b7280; }

  /* Parties */
  .party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 6px; }
  .party-name { font-weight: 600; color: #111827; margin-bottom: 2px; }
  .party-detail { color: #6b7280; font-size: 12px; line-height: 1.5; }

  /* Dates row */
  .dates-table { border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; margin-bottom: 32px; }
  .date-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 2px; }
  .date-value { font-size: 13px; color: #111827; }

  /* Line items table */
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  table.items thead tr { background: #f9fafb; }
  table.items th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
  table.items th.right { text-align: right; }
  table.items td { padding: 11px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #374151; }
  table.items td.right { text-align: right; }
  table.items td.desc { color: #111827; }

  /* Totals */
  table.totals { width: 260px; margin-left: auto; }
  table.totals td { padding: 5px 0; font-size: 13px; color: #6b7280; }
  table.totals td.right { text-align: right; }
  table.totals tr.grand td { border-top: 2px solid #e5e7eb; padding-top: 10px; font-size: 16px; font-weight: 700; color: #111827; }
  table.totals tr.due td { font-size: 15px; font-weight: 700; color: #4f46e5; padding-top: 4px; }
  .credit { color: #16a34a; }

  /* Payments */
  .payments { margin-top: 32px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
  .payments h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 10px; }
  table.payments-table { width: 100%; border-collapse: collapse; }
  table.payments-table td { padding: 4px 0; font-size: 12px; color: #6b7280; }
  table.payments-table td.right { text-align: right; }

  /* Footer */
  .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #9ca3af; }
</style>
</head>
<body>
<div class="page">

  @php
    $companyName    = $settings['company_name']    ?? config('app.name');
    $companyAddress = $settings['company_address'] ?? '';
    $currencySymbol = $settings['currency_symbol'] ?? '$';
    $logoPath       = $settings['logo_path']       ?? null;
  @endphp

  <!-- Header: logo/brand left, invoice # right -->
  <table width="100%" style="margin-bottom:40px;" cellpadding="0" cellspacing="0">
    <tr>
      <td valign="top">
        @if($logoPath)
          <img src="{{ storage_path('app/public/' . $logoPath) }}" alt="{{ $companyName }}" style="max-height:48px;max-width:180px;margin-bottom:4px;display:block;">
        @else
          <div class="brand">{{ $companyName }}</div>
        @endif
        <div class="brand-sub">{{ config('app.url') }}</div>
      </td>
      <td valign="top" align="right">
        <div class="invoice-meta-title">Invoice #{{ $invoice->id }}</div>
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
      </td>
    </tr>
  </table>

  <!-- Parties: Bill To left, From right -->
  <table width="100%" style="margin-bottom:32px;" cellpadding="0" cellspacing="0">
    <tr>
      <td valign="top" width="50%">
        <div class="party-label">Bill To</div>
        <div class="party-name">{{ $invoice->user->name }}</div>
        <div class="party-detail">{{ $invoice->user->email }}</div>
      </td>
      <td valign="top" width="50%" align="right">
        <div class="party-label">From</div>
        <div class="party-name">{{ $companyName }}</div>
        @if($companyAddress)
          <div class="party-detail" style="white-space:pre-line;">{{ $companyAddress }}</div>
        @endif
      </td>
    </tr>
  </table>

  <!-- Dates -->
  <table width="100%" class="dates-table" style="padding:14px 0;margin-bottom:32px;" cellpadding="0" cellspacing="0">
    <tr>
      <td style="padding:14px 40px 14px 0;" valign="top">
        <span class="date-label">Invoice Date</span>
        <span class="date-value">{{ \Carbon\Carbon::parse($invoice->date)->format('M d, Y') }}</span>
      </td>
      <td style="padding:14px 40px 14px 0;" valign="top">
        <span class="date-label">Due Date</span>
        <span class="date-value">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</span>
      </td>
      @if($invoice->paid_at)
      <td style="padding:14px 0;" valign="top">
        <span class="date-label">Paid On</span>
        <span class="date-value">{{ \Carbon\Carbon::parse($invoice->paid_at)->format('M d, Y') }}</span>
      </td>
      @endif
      <td></td>
    </tr>
  </table>

  <!-- Line items -->
  <table class="items">
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
  <table class="totals" cellpadding="0" cellspacing="0">
    @if($invoice->tax > 0)
    <tr>
      <td>Subtotal</td>
      <td class="right">{{ $currencySymbol }}{{ number_format($invoice->subtotal, 2) }}</td>
    </tr>
    <tr>
      <td>Tax ({{ $invoice->tax_rate }}%)</td>
      <td class="right">{{ $currencySymbol }}{{ number_format($invoice->tax, 2) }}</td>
    </tr>
    @endif
    <tr class="grand">
      <td>Total</td>
      <td class="right">{{ $currencySymbol }}{{ number_format($invoice->total, 2) }}</td>
    </tr>
    @if($invoice->credit_applied > 0)
    <tr>
      <td>Credit Applied</td>
      <td class="right credit">-{{ $currencySymbol }}{{ number_format($invoice->credit_applied, 2) }}</td>
    </tr>
    <tr class="due">
      <td>Amount Due</td>
      <td class="right">{{ $currencySymbol }}{{ number_format($invoice->amount_due, 2) }}</td>
    </tr>
    @endif
  </table>

  <!-- Payment history -->
  @if($invoice->payments->where('status','completed')->count())
  <div class="payments">
    <h3>Payments Received</h3>
    <table class="payments-table" cellpadding="0" cellspacing="0">
      @foreach($invoice->payments->where('status','completed') as $payment)
      <tr>
        <td>{{ ucfirst($payment->gateway) }} — {{ $payment->transaction_id }}</td>
        <td class="right">{{ $currencySymbol }}{{ number_format($payment->amount, 2) }} on {{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') }}</td>
      </tr>
      @endforeach
    </table>
  </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    Thank you for your business. Questions? Contact us at {{ config('mail.from.address') }}.
  </div>

</div>
</body>
</html>
