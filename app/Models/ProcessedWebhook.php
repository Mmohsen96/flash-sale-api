<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhook extends Model
{
    protected $fillable = ['order_id', 'idempotency_key', 'order_exists', 'status'];

    public function order()
    {
        return $this->hasOne(Order::class);
    }
}
