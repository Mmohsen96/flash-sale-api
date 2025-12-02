<?php

namespace Tests\Feature;

use App\Models\Hold;
use App\Models\Product;
use App\Models\ProcessedWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookBeforeOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_arriving_before_order_creation_is_retried()
    {
        $product = Product::create(['name' => 'P', 'price' => 10, 'stock' => 10]);

        // We will pick an order id that does NOT exist yet.
        $futureOrderId = DB::table('orders')->max('id') + 1;

        $key = 'before-order-key-' . uniqid();

        // Send webhook before order exists
        $r = $this->postJson('/api/payments/webhook', [
            'order_id' => $futureOrderId,
            'status' => 'failure',
            'idempotency_key' => $key,
        ]);

        // Should return 200 and create processed_webhook with order_exists = false
        $r->assertStatus(200);
        $this->assertDatabaseHas('processed_webhooks', [
            'idempotency_key' => $key,
            'order_exists' => false,
        ]);

        // Now simulate order creation with that exact id (insert by DB to fix id)
        // Create a hold first
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 1,
            'status' => 'not used',
            'expires_at' => now()->addMinutes(2),
        ]);

        // Insert order with specific id (use DB)
        DB::table('orders')->insert([
            'id' => $futureOrderId,
            'hold_id' => $hold->id,
            'status' => 'pre_payment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run retry command
        $this->artisan('webhooks:retry')->assertExitCode(0);

        // Now processed_webhook should be marked as order_exists = true and order cancelled
        $this->assertDatabaseHas('processed_webhooks', [
            'idempotency_key' => $key,
            'order_exists' => true,
            'status' => 'failure',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $futureOrderId,
            'status' => 'cancelled',
        ]);
    }
}
