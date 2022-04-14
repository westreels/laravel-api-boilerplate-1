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

class CreateSessionErrors extends \App\Models\BaseModel
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
    protected $table = 'log_createsession_errors';
     
    protected $fillable = [
        'uid', 'apikey', 'ownedBy', 'error_code', 'error_message', 'request'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];


    public static function insertCreateSessionError($apikey, $ownedBy, $error_code, $error_message, $request_link, $count = NULL) 
    {
            $selectParentApiSettings = GameoptionsParent::where('apikey_parent', $apikey)->first();

            /* Disable API key because of too many hourly errors */
            if($count !== 0) {
            if($selectParentApiSettings->hourly_spare_createsession_errors < 1) {
                $selectParentApiSettings->update([
                    'active' => 0,
                ]);
            }
            $selectParentApiSettings->update([
                'hourly_spare_createsession_errors' => $selectParentApiSettings->hourly_spare_createsession_errors - 1,
            ]);
            }
 

            $sessionLog = self::insert([
                'uid' =>  Uuid::generate(4),
                'apikey' => $apikey,
                'ownedBy' => $ownedBy,
                'error_code' => $error_code,
                'error_message' => $error_message,
                'request' => $request_link,
                'updated_at' => now(),
                'created_at' => now()
            ]);
        return true;
    }

}