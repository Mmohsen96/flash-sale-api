<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        $cacheKey = 'product_' . $product->id . '_availability';

        $availableStock = cache()->remember($cacheKey, 60, function () use ($product) {
            $activeHoldsQty = $product->holds()->where('status', 'not used')->sum('qty');
            return $product->stock - $activeHoldsQty;
        });

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'available_stock' => $availableStock,
        ]);
    }
}
