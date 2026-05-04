<?php

return [
    'table' => 'vouchers',

    'model' => BeyondCode\Vouchers\Models\Voucher::class,

    'relation_table' => 'account_voucher',

    'characters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',

    'prefix' => null,

    'suffix' => null,

    'mask' => '****-****-****',

    'separator' => '-',

    'user_model' => App\Models\Accounting\Account::class,
];