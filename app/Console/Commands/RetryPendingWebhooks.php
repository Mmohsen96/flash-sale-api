<?php

namespace App\Console\Commands;

use App\Models\Hold;
use App\Models\Order;
use App\Models\ProcessedWebhook;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryPendingWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry webhooks where order did not exist when first processed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pendingWebhooks = ProcessedWebhook::where('order_exists', false)->get();

        foreach ($pendingWebhooks as $webhook) {
            DB::transaction(function () use ($webhook) {

                $order = Order::where('id', $webhook->order_id)->lockForUpdate()->first();

                if (!$order) {
                    // still not created, will retry next schedule
                    return;
                }

                $hold = Hold::where('id', $order->hold_id)->lockForUpdate()->first();
                $product = $hold ? $hold->product()->lockForUpdate()->first() : null;

                if ($webhook->status === 'success') {
                    $order->update(['status' => 'paid']);
                    Log::info("Payment success", ['order_id' => $order->id, 'hold_id' => $order->hold_id]);

                } else {
                    $order->update(['status' => 'cancelled']);
                    Log::warning("Payment failed", ['order_id' => $order->id, 'hold_id' => $order->hold_id]);

                    // Clear product cache
                    Cache::forget('product_' . $hold->product_id . '_availability');

                    if ($hold && $hold->status === 'used') {
                        $hold->update(['status' => 'cancelled']);

                        // return the qty to the stock
                        if ($product) {
                            $product->increment('stock', $hold->qty);
                        }
                    }
                }

                // Mark webhook order as exist
                $webhook->update(['order_exists' => true]);
            });

            Log::info("Webhook retried", ['webhook_id' => $webhook->id, 'order_id' => $webhook->order_id]);
        }

        Log::info("RetryPendingWebhooks command completed");

    }
}
