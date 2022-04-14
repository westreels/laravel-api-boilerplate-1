<?php

namespace App\Models\Slotlayer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Slotlayer\Gameoptions;
use \Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CurrencyPrices extends \App\Models\BaseModel
{

   use HasFactory;
   public $timestamps = true;
    public $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'currencyprices';
     
    protected $fillable = [
        'id', 'currency', 'price'
    ];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    
    public static function cachedPrices($currency) {
        $currency = strtoupper($currency);
        $cachedPrices = Cache::get('cachedPrices:'.$currency);  

        if (!$cachedPrices) { 
            $cachedPrices = \App\Models\Slotlayer\CurrencyPrices::where('currency', $currency)->first()->price;
            
            Cache::put('cachedPrices:'.$currency, $cachedPrices, Carbon::now()->addMinutes(15));
        } 

        return $cachedPrices;
    }

}