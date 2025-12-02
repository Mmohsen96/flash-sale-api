<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Order;
use App\Models\ProcessedWebhook;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function webhook(Request $request){

        $request->validate([
            'order_id' => 'required|integer',
            'status' => 'required|in:success,failure',
            'idempotency_key' => 'required|string',
        ]);

        $orderId = $request->order_id;
        $status = $request->status;
        $idempotencyKey = $request->idempotency_key;


        return DB::transaction(function () use ($orderId, $status, $idempotencyKey) {

            // Lock the order
            $order = Order::query()->where('id', $orderId)->lockForUpdate()->first();

            // Check if webhook already processed (idempotency)
            $processed = ProcessedWebhook::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($processed) {
                return response()->json(['message' => 'Webhook already processed'], 200);
            }


            $orderExists = $order ? true : false;

            if ($orderExists) {
                // Lock the hold and the product
                $hold = Hold::where('id', $order->hold_id)->lockForUpdate()->first();
                $product = $hold ? $hold->product()->lockForUpdate()->first() : null;


                if ($status === 'success') {
                    $order->update(['status' => 'paid']);
                    Log::warning("Payment success", ['order_id' => $order->id, 'hold_id' => $order->hold_id]);

                } else {
                    $order->update(['status' => 'cancelled']);
                    Log::warning("Payment failed", ['order_id' => $order->id, 'hold_id' => $order->hold_id]);

                    // Clear product cache
                    Cache::forget('product_' . $hold->product_id . '_availability');

                    if ($hold && $hold->status === 'used') {
                        // Mark hold as cancelled to avoid any reuse
                        $hold->update(['status' => 'cancelled']);
                        // return the qty to the stock
                        if ($product) {
                            $product->increment('stock', $hold->qty);
                        }
                    }
                }
            }

            ProcessedWebhook::query()->create([
                'order_id' => $orderId,
                'idempotency_key' => $idempotencyKey,
                'order_exists' => $orderExists,
                'status' => $status,
            ]);

            return response()->json([
                'message' => $orderExists ? 'Webhook processed successfully' : 'Order not found yet, will retry later'
            ], 200);
        });
    }
}
