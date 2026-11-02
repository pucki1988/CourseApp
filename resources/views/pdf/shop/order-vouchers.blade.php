<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Gutscheine zur Bestellung #{{ $order->id }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 28px;
        }
       
        .voucher {
            border: 1px solid #ffc11e;
            
            padding: 20px;
            page-break-inside: avoid;
            text-align: center;
        }
        .voucher-label {
            background: #ffc11e;
            padding: 8px 8px;
            font-size: 10px;
            font-weight: normal;
            letter-spacing: 2px;
            margin-bottom: 0px;
            text-transform: uppercase;
            text-align: center;
        }
        .voucher-header {
            background: #ffc11e;
            padding: 0px 20px 20px;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 0px;
            text-transform: uppercase;
            text-align: center;
        }

        .voucher-footer {
            background: #ffc11e;
            padding: 4px;
            font-size: 10px;
            font-weight: normal;
            
            margin-bottom: 20px;
            text-transform: uppercase;
            text-align: center;
        }
        .product {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .amount {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 18px;
        }
        .code-label {
            color: #fff;
            background-color: #1f2937;
            font-size: 10px;
            padding: 2px;
            margin-bottom: 0px;
            text-transform: uppercase;
        }
        .code {
            background: #fff;
            border: 1px solid #1f2937;
            color: #1f2937;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 12px;
        }
        .terms {
            color: #4b5563;
            font-size: 6px;
            line-height: 1.5;
            margin-top: 24px;
            text-align: left;
        }
    </style>
</head>
<body>
    
    @foreach($vouchers as $voucher)
        <div class="voucher-label">Gutschein</div>
        <div class="voucher-header">Sportkurse</div>
        <div class="voucher">
            <div class="product">{{ $voucher['product_name'] }}</div>
            <div class="amount">{{ number_format(((int) $voucher['amount']) / 100, 2, ',', '.') }} {{ $voucher['currency'] }}</div>
            
            <div class="code">{{ $voucher['code'] }}</div>
            <div class="code-label">Gutscheincode</div>

            <div class="terms">
            Gültig bis <strong>{{ \Illuminate\Support\Carbon::create(now()->year + 3, 12, 31, 23, 59, 59)->format('d.m.Y') }}.</strong><br>
            Der Gutschein ist ausschließlich für Sportkurse der DJK-SG Schönbrunn einlösbar. Eine Einlösung für andere Veranstaltungen oder den Erwerb von Fanartikeln ist nicht möglich.
            Zur Einlösung ist ein User Account erforderlich. Der Gutschein wird nicht automatisch zugeordnet. Erstelle einen Account oder logge dich ein, um den Gutschein zu verwenden.
            </div>
        </div>
        <div class="voucher-footer">DJK-SG Schönbrunn am Lusen 1961 e.V. </div>
    @endforeach

    
</body>
</html>
