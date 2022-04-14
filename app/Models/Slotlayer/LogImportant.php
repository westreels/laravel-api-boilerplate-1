<?php

namespace App\Models\Slotlayer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Webpatser\Uuid\Uuid;
use App\Models\Slotlayer\GameoptionsParent;
use App\Models\Slotlayer\Gameoptions;

class LogImportant extends \App\Models\BaseModel
{

   use HasFactory;

   public $timestamps = true;
   public $primaryKey = 'uid';
   public $uuidKey = 'uid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'log_important';
     
    protected $fillable = [
        'uid', 'log_level', 'log_message', 'notified'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];


    public static function insertImportantLog($log_level, $log_message) {
            $sessionLog = self::insert([
                'uid' =>  Uuid::generate(4),
                'log_level' => $log_level,
                'log_message' => $log_message,
                'notified' => 0,
                'updated_at' => now(),
                'created_at' => now()
            ]);

        return true;
    }

}