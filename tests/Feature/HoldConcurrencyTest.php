<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Symfony\Component\Process\Process;

class HoldConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parallel_hold_attempts_do_not_oversell()
    {
        // Seed product with stock = 5
        $product = Product::create(['name' => 'Test', 'price' => 10, 'stock' => 5]);

        $concurrent = 10;
        $cmds = [];


        for ($i = 0; $i < $concurrent; $i++) {
            $cmds[] = [
                'command' => [
                    PHP_BINARY,
                    'artisan',
                    'serve',
                    '--env=testing',
                ],
            ];
        }


        $procs = [];
        for ($i = 0; $i < $concurrent; $i++) {
            $payload = json_encode(['product_id' => $product->id, 'qty' => 1]);
            $proc = new Process([
                'curl',
                '-s',
                '-X',
                'POST',
                url('/api/holds'),
                '-H',
                'Content-Type: application/json',
                '-d',
                $payload
            ]);
            $proc->start();
            $procs[] = $proc;
        }

        // wait for all processes
        foreach ($procs as $p) {
            $p->wait();
        }

        // Validate no oversell: sum of active holds (not used) <= product.stock
        $activeHoldsQty = \App\Models\Hold::where('product_id', $product->id)
            ->where('status', 'not used')
            ->sum('qty');

        $this->assertLessThanOrEqual(5, $activeHoldsQty);
    }
}
