<?php


use App\Http\Controllers\HoldController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;








Route::get('products/{product}',[ProductController::class, 'show'])->name('get-product');

Route::post('/hold', [HoldController::class, 'hold_stock'])->name('hold-product-stock');

Route::post('/order', [OrderController::class, 'create_order'])->name('create_order');

Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->name('payment-webhook');

Route::get('/metrics', [MetricsController::class, 'index'])->name('metrics');
