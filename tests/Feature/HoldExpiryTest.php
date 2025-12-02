<?php

namespace Tests\Feature;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_hold_expiry_releases_availability()
    {
        $product = Product::create(['name' => 'T', 'price' => 10, 'stock' => 5]);

        // create a hold that already expired
        $hold = Hold::create([
            'product_id' => $product->id,
            'qty' => 2,
            'status' => 'not used',
            'expires_at' => now()->subMinutes(5),
        ]);

        // Run expire command
        $this->artisan('holds:expire')->assertExitCode(0);

        $hold->refresh();
        $this->assertEquals('expired', $hold->status);

        // available stock should equal product.stock - active holds (active holds = 0)
        $active = Hold::where('product_id', $product->id)
            ->where('status', 'not used')
            ->sum('qty');
        $this->assertEquals(0, $active);
    }
}
