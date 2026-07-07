<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 44px 52px; }
        .letterhead { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 24px; }
        .letterhead h1 { font-size: 15px; color: #4f46e5; margin: 0; }
        .letterhead p { margin: 2px 0 0; color: #6b7280; font-size: 8.5px; }
        h2 { font-size: 14px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .meta td { padding: 1px 12px 1px 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.lines th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 4px 6px; }
        table.lines td { border-bottom: 1px solid #f3f4f6; padding: 4.5px 6px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 12px; width: 260px; margin-left: auto; }
        .totals td { padding: 2.5px 6px; }
        .totals .grand { border-top: 1.5px solid #4f46e5; font-weight: bold; font-size: 11px; }
        .matter-row td { background: #f9fafb; font-weight: bold; color: #374151; }
        .footer { margin-top: 34px; border-top: 1px solid #e5e7eb; padding-top: 8px; color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
    <div class="letterhead">
        <h1>IPMS Partners LLP</h1>
        <p>Intellectual Property Attorneys · 1 Inventors Square, London EC1 · ipms.example · VAT GB000111222</p>
    </div>

    <table width="100%">
        <tr>
            <td valign="top">
                <h2>{{ $invoice->status->value === 'draft' ? 'DRAFT INVOICE' : 'INVOICE' }} {{ $invoice->displayNumber() }}</h2>
                <table class="meta muted">
                    <tr><td>Issued</td><td>{{ $invoice->issued_at?->format('j F Y') ?? '—' }}</td></tr>
                    <tr><td>Due</td><td>{{ $invoice->due_at?->format('j F Y') ?? '—' }}</td></tr>
                    <tr><td>Currency</td><td>{{ $invoice->currency_code }}</td></tr>
                    @if ($invoice->entity?->billing_reference)
                        <tr><td>Your reference</td><td>{{ $invoice->entity->billing_reference }}</td></tr>
                    @endif
                </table>
            </td>
            <td valign="top" style="text-align: right;">
                <strong>{{ $invoice->entity?->name ?? $invoice->client?->name }}</strong><br>
                <span class="muted" style="white-space: pre-line;">{{ $invoice->entity?->billing_address ?? $invoice->entity?->address }}</span>
                @if ($invoice->entity?->vat_number)
                    <br><span class="muted">VAT {{ $invoice->entity->vat_number }}</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 58%;">Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines->groupBy('matter_id') as $lines)
                @if ($lines->first()->matter)
                    <tr class="matter-row">
                        <td colspan="4">{{ $lines->first()->matter->reference }} — {{ $lines->first()->matter->title }}</td>
                    </tr>
                @endif
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="num">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
                        <td class="num">{{ number_format($line->unit_amount, 2) }}</td>
                        <td class="num">{{ number_format($line->line_total, 2) }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $invoice->currency_code }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
        <tr><td>{{ $invoice->tax_name ?? 'Tax' }} ({{ rtrim(rtrim((string) $invoice->tax_pct, '0'), '.') }}%)</td><td class="num">{{ number_format($invoice->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="num">{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</td></tr>
        @if ($invoice->amountPaid() > 0)
            <tr><td>Paid</td><td class="num">-{{ number_format($invoice->amountPaid(), 2) }}</td></tr>
            <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ $invoice->currency_code }} {{ number_format($invoice->balance(), 2) }}</strong></td></tr>
        @endif
    </table>

    @if ($invoice->notes)
        <p class="muted" style="margin-top: 16px;">{{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Payment within {{ $invoice->due_at && $invoice->issued_at ? $invoice->issued_at->diffInDays($invoice->due_at) : 30 }} days.
        Generated by IPMS on {{ now()->format('j F Y') }}.
    </div>
</body>
</html>
