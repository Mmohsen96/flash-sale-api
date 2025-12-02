<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function create_order(Request $request)
    {

        $request->validate([
            'hold_id'=>'required|exists:holds,id'
        ]);

        $holdId = $request->hold_id;

        return DB::transaction(function () use ($holdId) {

            // Lock the hold row for update to prevent race conditions
            $hold = Hold::query()->where('id', $holdId)
                ->lockForUpdate()
                ->first();

            if (!$hold) {
                return response()->json(['error' => 'Hold not found'], 404);
            }

            if ($hold->status !== 'not used') {
                return response()->json(['error' => 'Hold is invalid, already '. $hold->status], 400);
            }

            // Lock the product to decrease the stock
            $product = Product::where('id', $hold->product_id)->lockForUpdate()->first();
            $product->decrement('stock', $hold->qty);

            // Create the order
            $order = Order::query()->create([
                'hold_id' => $hold->id,
                'status' => 'pre_payment',
            ]);

            // Mark hold as used
            $hold->update(['status' => 'used']);

            // Clear product cache
            Cache::forget('product_' . $hold->product_id . '_availability');

            return response()->json([
                'order_id' => $order->id,
                'hold_id' => $hold->id,
                'status' => $order->status,
            ], 201);
        });
    }
}
