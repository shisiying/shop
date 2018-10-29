<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductProperty extends Model
{
    protected $fillable = ['name','value'];
    //没有created_at 和 updated_at
    public $timestamps=false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
