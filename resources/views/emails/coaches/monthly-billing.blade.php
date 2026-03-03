<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Monatsabrechnung</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px;
            color: #333;
        }
        .header {
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            margin-bottom: 5px;
        }
        .summary {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        .summary-item strong {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #2563eb;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:hover {
            background-color: #f9fafb;
        }
        .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .amount {
            text-align: right;
            font-weight: 600;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .no-slots {
            padding: 30px;
            text-align: center;
            background-color: #fef3c7;
            border-radius: 5px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Monatsabrechnung {{ $billingData['month_name'] }}</h1>
        <p>Hallo {{ $billingData['coach']->name }},</p>
        <p>hier ist deine Abrechnung für {{ $billingData['month_name'] }}.</p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <span>Zeitraum:</span>
            <strong>{{ $billingData['month_name'] }}</strong>
        </div>
        <div class="summary-item">
            <span>Anzahl Termine:</span>
            <strong>{{ $billingData['total_slots'] }}</strong>
        </div>
        <div class="summary-item">
            <span>Gesamtvergütung:</span>
            <strong style="color: #2563eb; font-size: 1.2rem;">€ {{ number_format($billingData['total_compensation'], 2, ',', '.') }}</strong>
        </div>
    </div>

    @if(count($billingData['billing_items']) > 0)
        <h2>Termine im Detail</h2>
        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Kurs</th>
                    <th>Uhrzeit</th>
                    <th style="text-align: center;">Teilnehmer</th>
                    <th style="text-align: right;">Vergütung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($billingData['billing_items'] as $item)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item['date'])->translatedFormat('d.m.Y') }}</td>
                    <td>{{ $item['course_title'] }}</td>
                    <td>{{ \Carbon\Carbon::parse($item['start_time'])->format('H:i') }} - {{ \Carbon\Carbon::parse($item['end_time'])->format('H:i') }}</td>
                    <td style="text-align: center;">{{ $item['participant_count'] }}</td>
                    <td class="amount">€ {{ number_format($item['compensation'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;">Gesamt:</td>
                    <td class="amount">€ {{ number_format($billingData['total_compensation'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <div class="no-slots">
            <p><strong>Keine Termine</strong></p>
            <p>Für diesen Monat wurden keine Termine gefunden.</p>
        </div>
    @endif

    <div class="footer">
        <p>Bei Fragen zu deiner Abrechnung kannst du dich jederzeit an uns wenden.</p>
        <p>Viele Grüße</p>
    </div>
</body>
</html>
