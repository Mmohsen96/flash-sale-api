<?php

namespace App\Console\Commands;

use App\Models\Hold;
use Cache;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire holds that are past their expires_at';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        $expiredHolds = Hold::query()->where('status', 'not used')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredHolds as $hold) {
            $hold->update(['status' => 'expired']);

            Log::info("Hold expired", ['hold_id' => $hold->id, 'product_id' => $hold->product_id]);

            // Clear product cache
            Cache::forget('product_' . $hold->product_id . '_availability');
        }
            Log::info("ExpireHolds command completed");

    }
}
