<?php

namespace App\Http\Resources\Shop;

use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestPayment = $this->whenLoaded('payments', fn () => $this->payments->sortByDesc('id')->first());

        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'total_amount' => $this->total_amount,
            'paid_at' => $this->paid_at,
            'canceled_at' => $this->canceled_at,
            'meta' => $this->meta,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_type' => $item->product_type,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'meta' => $item->meta,
                ]);
            }),
            'payment' => $latestPayment ? new PaymentResource($latestPayment) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}