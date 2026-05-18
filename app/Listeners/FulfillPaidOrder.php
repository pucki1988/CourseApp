<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\Shop\OrderFulfillmentService;

class FulfillPaidOrder
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->orderFulfillmentService->fulfillPaidOrder($event->order);
    }
}