<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\OrderResource;
use App\Models\Shop\Product;
use App\Services\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function store(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => [$user ? 'nullable' : 'required', 'email', 'max:255'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $order = $this->orderService->createProductCheckout(
            $product,
            (int) ($validated['quantity'] ?? 1),
            $validated,
            $user
        );

        return new OrderResource($order);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return OrderResource::collection(
            $user->orders()->with(['items', 'payments'])->latest()->get()
        );
    }
}