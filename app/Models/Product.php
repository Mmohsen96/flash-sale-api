<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable=['name','stock','price'];

    public function holds(){
        return $this->hasMany(Hold::class,'product_id');
    }
}
