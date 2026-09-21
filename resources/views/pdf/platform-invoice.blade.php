<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11px; line-height: 1.45; }
        .header { width: 100%; border-bottom: 3px solid #167447; padding-bottom: 18px; }
        .brand { font-size: 23px; font-weight: bold; color: #12663e; }
        .muted { color: #64748b; }
        .title { font-size: 26px; font-weight: bold; text-align: right; letter-spacing: 1px; }
        .number { text-align: right; color: #475569; margin-top: 4px; }
        .block { margin-top: 24px; }
        .columns { width: 100%; }
        .columns td { width: 50%; vertical-align: top; }
        .label { color: #64748b; text-transform: uppercase; font-size: 9px; font-weight: bold; letter-spacing: .5px; }
        .value { margin-top: 3px; font-size: 12px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items th { background: #12663e; color: white; padding: 9px 8px; text-align: left; font-size: 10px; }
        .items td { border-bottom: 1px solid #dbe3ea; padding: 10px 8px; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 42%; margin-left: auto; margin-top: 18px; border-collapse: collapse; }
        .totals td { padding: 6px 8px; }
        .total td { background: #e8f5ed; color: #0f5132; font-size: 14px; font-weight: bold; border-top: 2px solid #167447; }
        .status { display: inline-block; padding: 4px 9px; border-radius: 10px; background: #e8f5ed; color: #0f5132; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        .notes { margin-top: 25px; padding: 12px; background: #f8fafc; border-left: 3px solid #94a3b8; }
        .footer { position: fixed; bottom: -8px; left: 0; right: 0; color: #64748b; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <table class="header"><tr>
        <td><div class="brand">{{ config('app.name', 'ElimuHub') }}</div><div class="muted">Platform subscription billing</div></td>
        <td><div class="title">INVOICE</div><div class="number">{{ $invoice->invoice_number }}</div></td>
    </tr></table>

    <table class="columns block"><tr>
        <td>
            <div class="label">Bill to</div>
            <div class="value"><strong>{{ $invoice->school?->name }}</strong><br>
                @if($invoice->school?->address){{ $invoice->school->address }}<br>@endif
                @if($invoice->school?->email){{ $invoice->school->email }}@endif
            </div>
        </td>
        <td>
            <div class="label">Invoice details</div>
            <div class="value">
                Issued: {{ ($invoice->sent_at ?? $invoice->created_at)?->format('d M Y') }}<br>
                Due: {{ $invoice->due_date?->format('d M Y') ?? 'On receipt' }}<br>
                Status: <span class="status">{{ $invoice->status }}</span>
            </div>
        </td>
    </tr></table>

    <div class="block"><strong>{{ $invoice->title }}</strong></div>
    <table class="items">
        <thead><tr><th>Description</th><th class="right">Quantity</th><th class="right">Unit price</th><th class="right">Amount</th></tr></thead>
        <tbody>@foreach($invoice->items as $item)<tr>
            <td>{{ $item->description }}</td>
            <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
            <td class="right">{{ $invoice->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
            <td class="right">{{ $invoice->currency }} {{ number_format((float) $item->amount, 2) }}</td>
        </tr>@endforeach</tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        @if((float) $invoice->discount_amount > 0)<tr><td>Discount</td><td class="right">- {{ $invoice->currency }} {{ number_format((float) $invoice->discount_amount, 2) }}</td></tr>@endif
        @if((float) $invoice->tax_amount > 0)<tr><td>Tax</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>@endif
        <tr class="total"><td>Total</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 2) }}</td></tr>
    </table>

    @if($invoice->notes)<div class="notes"><strong>Notes</strong><br>{{ $invoice->notes }}</div>@endif
    @if($invoice->rejection_reason)<div class="notes"><strong>School response</strong><br>{{ $invoice->rejection_reason }}</div>@endif
    <div class="footer">This is a system-generated invoice from {{ config('app.name', 'ElimuHub') }}.</div>
</body>
</html>
