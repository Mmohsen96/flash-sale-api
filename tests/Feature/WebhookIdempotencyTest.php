<?php

namespace Tests\Feature;

use App\Models\Hold;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProcessedWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_idempotency_same_key_repeated()
    {
        $product = Product::create(['name' => 'P', 'price' => 10, 'stock' => 10]);

        // Create hold + order
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 1,
            'status' => 'not used',
            'expires_at' => now()->addMinutes(2),
        ]);

        // Simulate order creation (this will decrement stock in your implementation)
        $response = $this->postJson('/api/order', ['hold_id' => $hold->id]);
        $response->assertStatus(201);
        $orderId = $response->json('order_id');

        $key = 'idem-key-123';

        // First webhook call
        $r1 = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => $key,
        ]);
        $r1->assertStatus(200);

        // Second webhook call with same key
        $r2 = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status' => 'success',
            'idempotency_key' => $key,
        ]);
        $r2->assertStatus(200);

        // Only one ProcessedWebhook with that key
        $this->assertDatabaseHas('processed_webhooks', ['idempotency_key' => $key]);

        $count = ProcessedWebhook::where('idempotency_key', $key)->count();
        $this->assertEquals(1, $count);

        // Order should be paid
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid']);
    }
}
