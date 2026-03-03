<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Monatsabrechnung {{ $billingData['month_name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 24px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 20px;
        }
        .muted {
            color: #4b5563;
            margin-bottom: 16px;
        }
        .summary {
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            margin-bottom: 16px;
        }
        .summary-row {
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            text-align: left;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .total td {
            font-weight: 700;
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <h1>Monatsabrechnung {{ $billingData['month_name'] }}</h1>
    <div class="muted">Verein: DJK SG Schönbrunn am Lusen</div>
    <div class="muted">Trainer: {{ $billingData['coach']->name }}</div>
    
    <div class="summary">
        <div class="summary-row">Zeitraum: <strong>{{ $billingData['month_name'] }}</strong></div>
        <div class="summary-row">Anzahl Termine: <strong>{{ $billingData['total_slots'] }}</strong></div>
        <div class="summary-row">Gesamtvergütung: <strong>€ {{ number_format($billingData['total_compensation'], 2, ',', '.') }}</strong></div>
    </div>

    @if(count($billingData['billing_items']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kurs</th>
                    <th>Uhrzeit</th>
                    <th class="center">Teilnehmer</th>
                    <th class="right">Vergütung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($billingData['billing_items'] as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($item['date'])->locale('de')->translatedFormat('d.m.Y') }}</td>
                        <td>{{ $item['course_title'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($item['start_time'])->format('H:i') }} - {{ \Carbon\Carbon::parse($item['end_time'])->format('H:i') }}</td>
                        <td class="center">{{ $item['participant_count'] }}</td>
                        <td class="right">€ {{ number_format($item['compensation'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="4" class="right">Gesamt</td>
                    <td class="right">€ {{ number_format($billingData['total_compensation'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p>Für diesen Monat wurden keine Termine gefunden.</p>
    @endif
</body>
</html>
