<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice #{{ $invoice->id }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr>
    <td align="center">

      <!-- Outer card -->
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- ── Header bar ── -->
        <tr>
          <td style="background:#4f46e5;border-radius:12px 12px 0 0;padding:28px 36px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td valign="middle">
                  @if($settings['logo_path'] ?? null)
                    <img src="{{ config('app.url') }}/storage/{{ $settings['logo_path'] }}"
                         alt="{{ $settings['company_name'] }}"
                         style="max-height:40px;max-width:160px;display:block;">
                  @else
                    <span style="font-size:22px;font-weight:700;color:#fff;letter-spacing:-0.5px;">{{ $settings['company_name'] }}</span>
                  @endif
                </td>
                <td valign="middle" align="right">
                  <span style="font-size:13px;color:rgba(255,255,255,0.7);">Invoice</span>
                  <div style="font-size:24px;font-weight:700;color:#fff;line-height:1.2;">#{{ $invoice->id }}</div>
                  @php
                    $badgeBg    = match($invoice->status) { 'paid' => '#dcfce7', 'overdue' => '#fee2e2', 'cancelled' => '#e5e7eb', default => '#fef9c3' };
                    $badgeColor = match($invoice->status) { 'paid' => '#166534', 'overdue' => '#991b1b', 'cancelled' => '#6b7280', default => '#854d0e' };
                  @endphp
                  <span style="display:inline-block;margin-top:6px;padding:3px 12px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:{{ $badgeBg }};color:{{ $badgeColor }};">
                    {{ ucfirst($invoice->status) }}
                  </span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── White body ── -->
        <tr>
          <td style="background:#fff;padding:36px;">

            <!-- Greeting -->
            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
              Hi <strong>{{ $invoice->user->name }}</strong>,
            </p>
            @if($invoice->status === 'paid')
              <p style="margin:0 0 28px;font-size:15px;color:#374151;line-height:1.6;">
                Your invoice has been <strong style="color:#16a34a;">paid in full</strong>. Thank you for your business! Please find the invoice summary below.
              </p>
            @elseif($invoice->status === 'overdue')
              <p style="margin:0 0 28px;font-size:15px;color:#374151;line-height:1.6;">
                Your invoice is <strong style="color:#dc2626;">overdue</strong>. Please settle the outstanding balance at your earliest convenience to avoid service interruption.
              </p>
            @else
              <p style="margin:0 0 28px;font-size:15px;color:#374151;line-height:1.6;">
                Please find your invoice below. Payment is due by <strong>{{ \Carbon\Carbon::parse($invoice->due_date)->format('F j, Y') }}</strong>.
              </p>
            @endif

            <!-- Bill From / Bill To -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
              <tr>
                <td width="50%" valign="top" style="padding-right:16px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:6px;">Bill To</div>
                  <div style="font-size:14px;font-weight:600;color:#111827;margin-bottom:2px;">{{ $invoice->user->name }}</div>
                  <div style="font-size:13px;color:#6b7280;">{{ $invoice->user->email }}</div>
                  @if($invoice->user->company ?? null)
                    <div style="font-size:13px;color:#6b7280;">{{ $invoice->user->company }}</div>
                  @endif
                </td>
                <td width="50%" valign="top" align="right" style="padding-left:16px;">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:6px;">From</div>
                  <div style="font-size:14px;font-weight:600;color:#111827;margin-bottom:2px;">{{ $settings['company_name'] }}</div>
                  @if($settings['company_address'] ?? null)
                    <div style="font-size:13px;color:#6b7280;white-space:pre-line;">{{ $settings['company_address'] }}</div>
                  @endif
                </td>
              </tr>
            </table>

            <!-- Dates row -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f9fafb;border-radius:8px;padding:14px 20px;margin-bottom:28px;">
              <tr>
                <td width="33%" valign="top">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:3px;">Invoice Date</div>
                  <div style="font-size:13px;color:#111827;">{{ \Carbon\Carbon::parse($invoice->date)->format('M d, Y') }}</div>
                </td>
                <td width="33%" valign="top">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:3px;">Due Date</div>
                  <div style="font-size:13px;color:#111827;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</div>
                </td>
                @if($invoice->paid_at)
                <td width="33%" valign="top">
                  <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:3px;">Paid On</div>
                  <div style="font-size:13px;color:#16a34a;font-weight:600;">{{ \Carbon\Carbon::parse($invoice->paid_at)->format('M d, Y') }}</div>
                </td>
                @endif
              </tr>
            </table>

            <!-- Line items table -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:0;border-collapse:collapse;">
              <thead>
                <tr style="background:#f9fafb;">
                  <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b7280;border-bottom:2px solid #e5e7eb;">Description</th>
                  <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b7280;border-bottom:2px solid #e5e7eb;width:50px;">Qty</th>
                  <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b7280;border-bottom:2px solid #e5e7eb;width:80px;">Unit</th>
                  <th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b7280;border-bottom:2px solid #e5e7eb;width:90px;">Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach($invoice->items as $item)
                <tr>
                  <td style="padding:12px 12px;font-size:13px;color:#111827;border-bottom:1px solid #f3f4f6;">{{ $item->description }}</td>
                  <td style="padding:12px 12px;font-size:13px;color:#374151;text-align:right;border-bottom:1px solid #f3f4f6;">{{ $item->quantity }}</td>
                  <td style="padding:12px 12px;font-size:13px;color:#374151;text-align:right;border-bottom:1px solid #f3f4f6;">{{ $currencySymbol }}{{ number_format((float)$item->unit_price, 2) }}</td>
                  <td style="padding:12px 12px;font-size:13px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">{{ $currencySymbol }}{{ number_format((float)$item->total, 2) }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>

            <!-- Totals block -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:0;">
              <tr>
                <td><!-- spacer --></td>
                <td width="260" align="right">
                  <table width="260" cellpadding="0" cellspacing="0" style="margin-left:auto;">
                    @if((float)$invoice->tax > 0)
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#6b7280;border-top:1px solid #f3f4f6;">Subtotal</td>
                      <td style="padding:6px 0;font-size:13px;color:#374151;text-align:right;border-top:1px solid #f3f4f6;">{{ $currencySymbol }}{{ number_format((float)$invoice->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                      <td style="padding:6px 0;font-size:13px;color:#6b7280;">Tax ({{ $invoice->tax_rate }}%)</td>
                      <td style="padding:6px 0;font-size:13px;color:#374151;text-align:right;">{{ $currencySymbol }}{{ number_format((float)$invoice->tax, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                      <td style="padding:10px 0 8px;font-size:15px;font-weight:700;color:#111827;border-top:2px solid #e5e7eb;">Total</td>
                      <td style="padding:10px 0 8px;font-size:15px;font-weight:700;color:#111827;text-align:right;border-top:2px solid #e5e7eb;">{{ $currencySymbol }}{{ number_format((float)$invoice->total, 2) }}</td>
                    </tr>
                    @if((float)($invoice->credit_applied ?? 0) > 0)
                    <tr>
                      <td style="padding:4px 0;font-size:13px;color:#6b7280;">Credit Applied</td>
                      <td style="padding:4px 0;font-size:13px;color:#16a34a;font-weight:600;text-align:right;">-{{ $currencySymbol }}{{ number_format((float)$invoice->credit_applied, 2) }}</td>
                    </tr>
                    @endif
                    @if($invoice->status !== 'paid')
                    <tr>
                      <td style="padding:6px 0 2px;font-size:16px;font-weight:700;color:#4f46e5;">Amount Due</td>
                      <td style="padding:6px 0 2px;font-size:18px;font-weight:700;color:#4f46e5;text-align:right;">{{ $currencySymbol }}{{ number_format((float)$invoice->amount_due, 2) }}</td>
                    </tr>
                    @endif
                  </table>
                </td>
              </tr>
            </table>

            <!-- Notes -->
            @if($invoice->notes ?? null)
            <div style="margin-top:24px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:6px;">Notes</div>
              <div style="font-size:13px;color:#374151;line-height:1.6;white-space:pre-line;">{{ $invoice->notes }}</div>
            </div>
            @endif

            <!-- CTA button -->
            @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:32px;">
              <tr>
                <td align="center">
                  <a href="{{ route('client.invoices.show', $invoice->id) }}"
                     style="display:inline-block;background:#4f46e5;color:#fff;font-size:14px;font-weight:600;text-decoration:none;padding:13px 32px;border-radius:8px;letter-spacing:0.01em;">
                    View Invoice &amp; Pay →
                  </a>
                </td>
              </tr>
            </table>
            @elseif($invoice->status === 'paid')
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:32px;">
              <tr>
                <td align="center">
                  <a href="{{ route('client.invoices.download', $invoice->id) }}"
                     style="display:inline-block;background:#059669;color:#fff;font-size:14px;font-weight:600;text-decoration:none;padding:13px 32px;border-radius:8px;letter-spacing:0.01em;">
                    Download Receipt →
                  </a>
                </td>
              </tr>
            </table>
            @endif

            <!-- Payment history (paid invoices) -->
            @if($invoice->payments->where('status','completed')->count())
            <div style="margin-top:32px;padding-top:24px;border-top:1px solid #e5e7eb;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;margin-bottom:12px;">Payments Received</div>
              @foreach($invoice->payments->where('status','completed') as $payment)
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px;">
                <tr>
                  <td style="font-size:13px;color:#374151;">{{ ucfirst($payment->gateway) }}</td>
                  <td style="font-size:13px;color:#374151;text-align:right;">
                    <strong>{{ $currencySymbol }}{{ number_format((float)$payment->amount, 2) }}</strong>
                    @if($payment->paid_at)
                      <span style="color:#9ca3af;font-size:12px;margin-left:8px;">{{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') }}</span>
                    @endif
                  </td>
                </tr>
              </table>
              @endforeach
            </div>
            @endif

          </td>
        </tr>

        <!-- ── Footer ── -->
        <tr>
          <td style="background:#f9fafb;border-radius:0 0 12px 12px;border-top:1px solid #e5e7eb;padding:20px 36px;text-align:center;">
            <p style="margin:0 0 4px;font-size:12px;color:#9ca3af;">
              {{ $settings['company_name'] }} · Questions? <a href="mailto:{{ config('mail.from.address') }}" style="color:#6b7280;text-decoration:none;">{{ config('mail.from.address') }}</a>
            </p>
            <p style="margin:0;font-size:11px;color:#d1d5db;">
              This email was sent to {{ $invoice->user->email }} regarding Invoice #{{ $invoice->id }}.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
