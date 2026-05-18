<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ProductResource;
use App\Models\Shop\Product;

class ProductController extends Controller
{
    public function index()
    {
        return ProductResource::collection(
            Product::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get()
        );
    }
}