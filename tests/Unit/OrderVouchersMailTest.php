<?php

use App\Mail\OrderVouchersMail;
use App\Models\Shop\Order;

uses(Tests\TestCase::class);

it('attaches a pdf containing the ordered voucher codes', function () {
    $order = new Order;
    $order->id = 123;
    $mail = new OrderVouchersMail($order, [
        [
            'code' => 'SPORT-ABC-123',
            'amount' => 2500,
            'currency' => 'EUR',
            'product_name' => 'Sportgutschein',
        ],
    ]);

    $attachment = $mail->attachments()[0];

    expect($attachment->as)->toBe('gutscheine-bestellung-123.pdf')
        ->and($attachment->mime)->toBe('application/pdf');

    $pdf = $attachment->attachWith(
        fn (string $path) => $path,
        fn (Closure $data) => $data(),
    );

    expect($pdf)->toStartWith('%PDF');
});

it('renders each voucher code inside its voucher design', function () {
    $html = view('pdf.shop.order-vouchers', [
        'order' => tap(new Order, fn (Order $order) => $order->id = 123),
        'vouchers' => [
            [
                'code' => 'SPORT-ABC-123',
                'amount' => 2500,
                'currency' => 'EUR',
                'product_name' => 'Sportgutschein',
            ],
        ],
    ])->render();

    expect($html)
        ->toContain('class="voucher"')
        ->toContain('SPORT-ABC-123')
        ->toContain('Sportgutschein')
        ->toContain('25,00 EUR');
});
