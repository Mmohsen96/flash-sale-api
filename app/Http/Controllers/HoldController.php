<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Product;
use Cache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HoldController extends Controller
{
    public function hold_stock(Request $request){

        $request->validate([
            'product_id'=>'required|integer|exists:products,id',
            'qty'=>'required|integer|min:1'
        ]);

        $productId = $request->product_id;
        $qty = $request->qty;

        return DB::transaction(function () use ($productId, $qty) {

            // Lock the product row to prevent race conditions
            $product = Product::query()->where('id', $productId)->lockForUpdate()->first();

            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            // Calculate active holds for this product
            $activeHoldsQty = Hold::query()->where('product_id', $productId)
                ->where('status', 'not used')
                ->sum('qty');

            $availableStock = $product->stock - $activeHoldsQty;

            if ($availableStock < $qty) {
                return response()->json([
                    'error' => 'Not enough stock',
                    'available_stock' => $availableStock,
                ], 400);
            }

            // Create hold
            $hold = Hold::query()->create([
                'product_id' => $productId,
                'qty' => $qty,
                'status' => 'not used',
                'expires_at' => Carbon::now()->addMinutes(2),
            ]);

            // Clear product cache
            Cache::forget('product_' . $productId . '_availability');

            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
            ]);
        });
    }
}
