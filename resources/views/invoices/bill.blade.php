<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $invoice['number'] }}</title>
<style>
    @page { margin: 34px 40px; }
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #1A1F23;
        font-size: 12px;
        line-height: 1.45;
        margin: 0;
    }
    .muted { color: #5C6670; }
    .accent { color: #3D7A6B; }
    .right { text-align: right; }
    .small { font-size: 10px; }

    /* Header */
    .head { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .brand-badge {
        display: inline-block;
        width: 34px; height: 34px;
        background: #0F2A3D; color: #fff;
        border-radius: 8px;
        font-size: 18px; font-weight: 700;
        text-align: center; line-height: 34px;
        margin-right: 8px;
    }
    .facility {
        font-size: 19px; font-weight: 700; color: #0F2A3D;
        letter-spacing: 0.3px;
    }
    .invoice-title {
        font-size: 26px; font-weight: 700; color: #0F2A3D;
        letter-spacing: 3px;
    }
    .rule { border-bottom: 3px solid #0F2A3D; height: 0; margin: 14px 0 18px; }

    .meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .meta td { vertical-align: top; padding: 0; }
    .label {
        font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px;
        color: #94A0A8; font-weight: 700; margin-bottom: 2px;
    }
    .strong { font-weight: 700; color: #1A1F23; }

    .badge {
        display: inline-block; padding: 3px 11px; border-radius: 11px;
        font-size: 10px; font-weight: 700; letter-spacing: 0.4px;
    }
    .badge-open { background: #F6E9DA; color: #8A551F; }
    .badge-part { background: #E7EDF1; color: #1B3F57; }
    .badge-paid { background: #E3EEEA; color: #25524A; }
    .badge-void { background: #EFEBE2; color: #5C6670; }

    /* Items */
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items thead th {
        background: #0F2A3D; color: #fff;
        text-align: left; padding: 8px 10px;
        font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;
        font-weight: 700;
    }
    table.items tbody td {
        padding: 7px 10px; border-bottom: 1px solid #E7EDF1;
    }
    table.items tbody tr:nth-child(even) td { background: #F7F9FA; }
    .src {
        display: inline-block; padding: 1px 7px; border-radius: 8px;
        font-size: 9px; font-weight: 700; text-transform: capitalize;
        background: #EFEBE2; color: #5C6670;
    }
    .src-pharmacy { background: #EDE7F6; color: #4A2E7A; }
    .src-laboratory { background: #E4EEF3; color: #1B3F57; }
    .src-consultation { background: #E3EEEA; color: #25524A; }

    /* Totals */
    .totals { width: 46%; margin-left: 54%; margin-top: 16px; border-collapse: collapse; }
    .totals td { padding: 5px 10px; }
    .totals .t-label { color: #5C6670; }
    .totals .t-val { text-align: right; font-weight: 700; }
    .totals .grand td {
        border-top: 2px solid #0F2A3D; font-size: 15px;
        color: #0F2A3D; padding-top: 8px;
    }
    .totals .balance td { color: #8A551F; }

    .paid-stamp {
        margin-top: 18px;
        display: inline-block;
        border: 3px solid #3D7A6B; color: #25524A;
        padding: 6px 18px; border-radius: 8px;
        font-size: 18px; font-weight: 700; letter-spacing: 3px;
        transform: rotate(-4deg);
    }

    .section-title {
        font-size: 11px; font-weight: 700; color: #0F2A3D;
        text-transform: uppercase; letter-spacing: 0.6px;
        margin: 22px 0 6px;
    }
    table.pays { width: 100%; border-collapse: collapse; }
    table.pays td { padding: 5px 0; border-bottom: 1px solid #EFEBE2; }

    .footer {
        margin-top: 26px; border-top: 1px solid #E7EDF1;
        padding-top: 10px; color: #94A0A8; font-size: 10px;
    }
</style>
</head>
<body>

    {{-- Header --}}
    <table class="head">
        <tr>
            <td style="width:60%;">
                <span class="brand-badge">M</span>
                <span class="facility">{{ $facility['name'] }}</span>
                <div class="muted small" style="margin-top:6px;">
                    {{ $facility['address'] }}<br>
                    {{ $facility['contact'] }}
                </div>
            </td>
            <td style="width:40%;" class="right">
                <div class="invoice-title">INVOICE</div>
                <div class="muted small" style="margin-top:6px;">
                    <span class="strong">{{ $invoice['number'] }}</span><br>
                    Issued {{ $invoice['date'] }}
                </div>
                <div style="margin-top:8px;">
                    <span class="badge {{ $invoice['badge_class'] }}">{{ $invoice['status_label'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Bill to --}}
    <table class="meta">
        <tr>
            <td style="width:60%;">
                <div class="label">Bill to</div>
                <div class="strong" style="font-size:14px;">{{ $patient['name'] }}</div>
                <div class="muted small">
                    File No: {{ $patient['file_number'] }}
                    @if($patient['detail']) &middot; {{ $patient['detail'] }} @endif
                </div>
            </td>
            <td style="width:40%;" class="right">
                <div class="label">Amount due</div>
                <div class="strong accent" style="font-size:18px;">{{ $money($totals['balance'] > 0 ? $totals['balance'] : 0) }}</div>
            </td>
        </tr>
    </table>

    {{-- Charges --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:47%;">Description</th>
                <th style="width:14%;">Source</th>
                <th style="width:8%;" class="right">Qty</th>
                <th style="width:13%;" class="right">Unit</th>
                <th style="width:13%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($charges as $i => $c)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>
                        {{ $c['description'] }}
                        @if($c['at'])<div class="muted small">{{ $c['at'] }}</div>@endif
                    </td>
                    <td><span class="src src-{{ $c['source'] }}">{{ $c['source'] }}</span></td>
                    <td class="right">{{ $c['quantity'] }}</td>
                    <td class="right">{{ $money($c['unit_price']) }}</td>
                    <td class="right strong">{{ $money($c['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="text-align:center; padding:18px;">No charges on this bill.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td class="t-label">Subtotal</td>
            <td class="t-val">{{ $money($totals['total']) }}</td>
        </tr>
        <tr>
            <td class="t-label">Paid</td>
            <td class="t-val">{{ $money($totals['paid']) }}</td>
        </tr>
        <tr class="grand balance">
            <td>Balance due</td>
            <td class="t-val">{{ $money($totals['balance']) }}</td>
        </tr>
    </table>

    @if($totals['balance'] <= 0 && $totals['total'] > 0)
        <div class="paid-stamp">PAID</div>
    @endif

    {{-- Payments --}}
    @if(count($payments))
        <div class="section-title">Payments received</div>
        <table class="pays">
            @foreach($payments as $p)
                <tr>
                    <td style="width:20%;" class="strong">{{ $money($p['amount']) }}</td>
                    <td style="width:20%;" class="muted">{{ $p['method'] }}</td>
                    <td style="width:35%;" class="muted small">{{ $p['reference'] ? 'Ref: '.$p['reference'] : '' }}</td>
                    <td style="width:25%;" class="right muted small">{{ $p['at'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        This is a computer-generated invoice from {{ $facility['name'] }} and is valid without a signature.
        Generated {{ $invoice['generated_at'] }}.
    </div>

</body>
</html>
