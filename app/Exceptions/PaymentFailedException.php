<?php

namespace App\Exceptions;

use Exception;

class PaymentFailedException extends Exception
{
    public function __construct(
        string $message = 'Zahlung konnte nicht verarbeitet werden.',
        int $code = 0
    ) {
        parent::__construct($message, $code);
    }
}
