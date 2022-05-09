<?php

namespace App\Models\Slotlayer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gametransactions extends \App\Models\BaseModel
{
   use HasFactory;
   
   public $timestamps = true;
   public $primaryKey = 'id';

    protected $table = 'gametransactions';

    protected $fillable = [
        'id', 'casinoid', 'player', 'ownedBy', 'bet', 'win', 'usd_exchange', 'currency', 'gameid', 'txid', 'final', 'type', 'access_profile' 'callback_state', 'rawdata', 'roundid',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    
}