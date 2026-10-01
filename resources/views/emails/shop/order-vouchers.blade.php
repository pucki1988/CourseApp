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
<p>Der Gutschein ist ausschließlich für Sportkurse der DJK-SG Schönbrunn einlösbar. Eine Einlösung für andere Veranstaltungen oder den Erwerb von Fanartikeln ist nicht möglich.</p>
<p>Der Gutschein ist ab Ende des Kaufjahres drei Jahre lang gültig. Gültig bis ({{ \Illuminate\Support\Carbon::create(now()->year + 3, 12, 31, 23, 59, 59)->format('d.m.Y') }})</p>
<p>Zur Einlösung des Gutscheins ist ein User Account erforderlich.</p>
<p>Der Gutschein wird nicht automatisch zugeordnet. Erstelle einen Account oder logge dich ein, um den Gutschein zu verwenden.</p>
<p>Sportliche Grüße</p>
</body>
</html>