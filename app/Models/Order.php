<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['hold_id', 'status'];

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    public function payment()
    {
        return $this->belongsTo(ProcessedWebhook::class,'order_id');
    }
}
