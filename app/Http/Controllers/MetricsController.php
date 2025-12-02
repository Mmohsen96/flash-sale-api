<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Models\Order;
use App\Models\ProcessedWebhook;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function index()
    {
        return response()->json([
            'timestamp' => now()->toDateTimeString(),
            'active_holds' => Hold::where('status', 'not used')->count(),
            'expired_holds' => Hold::where('status', 'expired')->count(),
            'used_holds' => Hold::where('status', 'used')->count(),

            'total_orders' => Order::count(),
            'paid_orders' => Order::where('status', 'paid')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'pre_payment_orders' => Order::where('status', 'pre_payment')->count(),

            'pending_webhook_retries' => ProcessedWebhook::where('order_exists', false)->count(),
            'processed_webhooks' => ProcessedWebhook::count(),
        ]);
    }
}
