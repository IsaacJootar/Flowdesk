<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vendor Statement - {{ $vendor->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #0f172a; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #475569; font-size: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        th { background: #f1f5f9; }
        .num { text-align: right; }
        .summary { margin-top: 18px; width: 340px; }
        .summary td { border: 1px solid #cbd5e1; padding: 8px; }
        .summary td:first-child { background: #f8fafc; font-weight: 600; }
        @media print {
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
    <h1>Vendor Statement</h1>
    <div class="muted">
        Vendor: {{ $vendor->name }} |
        Period: {{ $filters['from'] ?: 'Start' }} to {{ $filters['to'] ?: 'Today' }} |
        Currency: {{ $summary['currency'] }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Description</th>
                <th>Status</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['date'] ?? '-' }}</td>
                    <td>{{ strtoupper((string) ($row['type'] ?? '')) }}</td>
                    <td>{{ $row['reference'] ?? '-' }}</td>
                    <td>{{ $row['description'] ?? '-' }}</td>
                    <td>{{ $row['status'] ?? '-' }}</td>
                    <td class="num">{{ \App\Support\Money::formatPlain((int) ($row['debit'] ?? 0), 2) }}</td>
                    <td class="num">{{ \App\Support\Money::formatPlain((int) ($row['credit'] ?? 0), 2) }}</td>
                    <td class="num">{{ \App\Support\Money::formatPlain((int) ($row['balance'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No statement rows in selected range.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tbody>
            <tr>
                <td>Invoice Total</td>
                <td class="num">{{ \App\Support\Money::formatPlain((int) $summary['invoice_total'], 2) }}</td>
            </tr>
            <tr>
                <td>Payment Total</td>
                <td class="num">{{ \App\Support\Money::formatPlain((int) $summary['payment_total'], 2) }}</td>
            </tr>
            <tr>
                <td>Closing Balance</td>
                <td class="num">{{ \App\Support\Money::formatPlain((int) $summary['balance'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
