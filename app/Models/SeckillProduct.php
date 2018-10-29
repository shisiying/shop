<?php

namespace App\Models;

use App\Services\ProductService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SeckillProduct extends Model
{
    protected $fillable = ['start_at',"end_at"];
    protected $dates =['start_at',"end_at"];

    public $timestamps =false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    //当前时间早于秒杀时间返回true
    public function getIsBeforeStartAttribute()
    {
        return Carbon::now()->lt($this->start_at);
    }

    //当前时间晚于秒杀结束时返回true
    public function getIsAfterEndAttribute()
    {
        return Carbon::now()->gt($this->end_at);
    }
}
