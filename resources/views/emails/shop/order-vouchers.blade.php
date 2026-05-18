<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Deine Gutscheincodes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; color: #1f2937; }
        .code { font-family: Consolas, monospace; font-size: 1.1rem; font-weight: bold; }
        .voucher { margin-bottom: 12px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
    </style>
</head>
<body>
<p>Hallo,</p>
<p>vielen Dank fuer deine Bestellung #{{ $order->id }}.</p>
<p>Hier sind deine Gutscheincodes:</p>

@foreach($vouchers as $voucher)
    <div class="voucher">
        <div><strong>{{ $voucher['product_name'] }}</strong></div>
        <div class="code">{{ $voucher['code'] }}</div>
        <div>{{ number_format(((int) $voucher['amount']) / 100, 2, ',', '.') }} {{ $voucher['currency'] }}</div>
    </div>
@endforeach

<p>Die Gutscheine werden nicht automatisch zugeordnet. Bitte loese den Code in deinem Gutschein-Wallet ein.</p>
<p>Viele Gruesse</p>
</body>
</html>