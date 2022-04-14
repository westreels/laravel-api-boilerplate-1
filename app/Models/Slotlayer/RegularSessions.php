<?php

namespace App\Models\Slotlayer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Slotlayer\Gameoptions;
use \Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegularSessions extends \App\Models\BaseModel
{

   use HasFactory;

   public $timestamps = true;
   public $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'regular_sessions';
     
    protected $fillable = [
        'id', 'casino_id', 'session_id', 'currency', 'player_id', 'player_meta', 'player_ip', 'game', 'request_ip', 'created_at', 'updated_at', 'visited', 'active', 'extra_currency'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

}