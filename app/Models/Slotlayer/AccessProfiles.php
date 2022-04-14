<?php

namespace App\Models\Slotlayer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Slotlayer\Gameoptions;
use \Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccessProfiles extends \App\Models\BaseModel
{

   use HasFactory;
   public $timestamps = true;
    protected $dateFormat = 'U';
    public $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'access_profiles';
     
    protected $fillable = [
        'id', 'profile_name', 'branded', 'api_dk', 'api_evo', 'max_entries_sessions', 'max_hourly_callback_errors', 'max_hourly_createsession_errors', 'max_hourly_demosessions', 'active',
    ];

    protected $hidden = [
        'api_dk',
        'api_evo',
        'deleted_at',
        'email_verified_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',

        'active' => 'boolean',
        'providers' => 'array',
    ];

    public static function profilesCached()
    {
        $profilesCached = Cache::get('profilesCached');  

        if (!$profilesCached) { 
            $profilesCached = self::all()->where('active', '!=', 0);

            /* Cache access profiles, time depending on the environment */
            if(env('APP_ENV') === 'staging') {
                $stagingCache = env('globalStagingCache');
                Cache::put('profilesCached', $profilesCached, Carbon::now()->addMinutes($stagingCache));
            } else {
                Cache::put('profilesCached', $profilesCached, Carbon::now()->addMinutes(10));
            }
        }

        return $profilesCached;
    }

    public static function completeProfilesCached()
    {
        $profilesCached = Cache::get('completeProfilesCached');  

        if (!$profilesCached) { 
            $profilesCached = self::all()->where('active', '!=', 0);
            foreach($profilesCached as $arrayProfile) {
                $providers = self::getProviders($arrayProfile['id']);
                $array[] = $arrayProfile;
                $array[] = array('access' => $providers);
            }

            $profilesCached = $array;
            
            /* Cache access profiles, time depending on the environment */
            if(env('APP_ENV') === 'staging') {
                $stagingCache = env('globalStagingCache');
                Cache::put('completeProfilesCached', $profilesCached, Carbon::now()->addMinutes($stagingCache));
            } else {
                Cache::put('completeProfilesCached', $profilesCached, Carbon::now()->addMinutes(10));
            }
        }

        return $profilesCached;
    }

    public static function getProviders($profile)
    {   
        $getProviders = Cache::get('providers:'.$profile);  

        if (!$getProviders) { 
            $getProviders = AccessProviders::where('access_profile', $profile)->get();

            if(env('APP_ENV') === 'staging') {
                $stagingCache = env('globalStagingCache');
                Cache::put('providers:'.$profile, $getProviders, Carbon::now()->addMinutes($stagingCache));
            } else {
                Cache::put('providers:'.$profile, $getProviders, Carbon::now()->addMinutes(10));
            }
        }

        return $getProviders;
    }

    /* Example:
               $get = self::selectProfile('1', 'price', 'pragmatic');
               $get = self::selectProfile('1', 'all');
    */
    public static function selectProfile($id, $type, $provider = null)
    {

        if($type === 'price') {
            $providers = self::getProviders($id);
            return array('ggr_price' => $providers->where('provider', $provider)->first()->price);
        }

        $selectProfile = Cache::get('profile:'.$id);  

        if (!$selectProfile) { 
                $arrayProfile = self::all()->where('id', '=', $id);
                $providers = self::getProviders($id);
                $array[] = $arrayProfile;
                $array[] = array('access' => $providers);

            $selectProfile = $array;
            /* Cache access profiles, time depending on the environment */
            if(env('APP_ENV') === 'staging') {
                $stagingCache = env('globalStagingCache');
                Cache::put('profile:'.$id, $selectProfile, Carbon::now()->addMinutes($stagingCache));
            } else {
                Cache::put('profile:'.$id, $selectProfile, Carbon::now()->addMinutes(10));
            }
        }

        if($type === 'all') {
            return $selectProfile;
        }

        return $selectProfile;
    }


    public static function forgetCache()
    {
        Cache::forget('profilesCached');
    }

    public static function testRetrieve()
    {
        if(env('APP_ENV') === 'staging') {
            Cache::forget('profilesCached');
            try {
            $profilesCached = self::profilesCached();
            } catch (Throwable $e) {
                return 'Error'.$e;
            }

            return $profilesCached;
        }
    }

}