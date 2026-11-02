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
        .vouchers-container {
            border: 2px dotted #1f2937;
            padding: 10px;
        }
        .voucher {
            border-left: 1px solid #1f2937;
            border-right: 1px solid #1f2937;
            padding: 20px;
            page-break-inside: avoid;
            text-align: center;
        }
        .logo {
            width: 53px;
            
        }
        .voucher-logo {
            text-align: center;
            background: #ffc11e;
            padding: 10px 0 0;
            border-left: 1px solid #1f2937;
            border-right: 1px solid #1f2937;
        }

        .voucher-label {
            background: #ffc11e;
            border-bottom: 2px solid #1f2937;
            border-top: 1px solid #1f2937;
            padding: 8px 8px;
            font-size: 10px;
            font-weight: normal;
            letter-spacing: 2px;
            margin-bottom: 0px;
            text-transform: uppercase;
            text-align: center;
            border-left: 1px solid #1f2937;
            border-right: 1px solid #1f2937;
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
            border-left: 1px solid #1f2937;
            border-right: 1px solid #1f2937;
        }

        .voucher-footer {
            background: #ffc11e;
            padding: 4px;
            font-size: 10px;
            font-weight: normal;
            border-left: 1px solid #1f2937;
            border-right: 1px solid #1f2937;
            border-bottom: 1px solid #1f2937;
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
    
    @php
        $logoPath = public_path('logosg.svg');
        $logo = is_file($logoPath) ? 'data:image/svg+xml;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="vouchers-container">
    @foreach($vouchers as $voucher)
       
        <div class="voucher-label">Gutschein</div>
        <div class="voucher-logo"> 
        @if($logo)
            <img src="{{ $logo }}" class="logo" alt="">
        @endif
        </div>
        
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
</div>
    
</body>
</html>
