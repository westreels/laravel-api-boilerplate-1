<?php

namespace App\Http\Controllers\Slotlayer;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Specialtactics\L5Api\Http\Controllers\RestfulController as BaseController;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Http;
use App\Models\Slotlayer\Gameoptions;
use App\Models\Slotlayer\CreateSessionErrors;
use App\Models\Slotlayer\DemoSessions;
use App\Models\Slotlayer\RegularSessions;
use App\Models\Slotlayer\AccessProfiles;
use App\Models\Slotlayer\GameoptionsParent;
use \Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Dingo\Api\Routing\Helpers;
use Dingo\Api\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Webpatser\Uuid\Uuid;

class AggregationController extends \App\Http\Controllers\Controller
{
    public static function createRandomVal($val) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        srand((double)microtime() * 1000000);
        $i = 0;
        $pass = '';
        while ($i < $val) {
            $num = rand() % 64;
            $tmp = substr($chars, $num, 1);
            $pass = $pass . $tmp;
            $i++;
        }
        return $pass;
    }

    public static function encryptCasinoToken($plaintext, $password) 
     {
        //self::decryptCasinoToken(hex2bin($encryptedPID), $decryptID)
        $method = "AES-256-CBC";
        $key = hash('sha256', $password, true);
        $iv = openssl_random_pseudo_bytes(16);

        $ciphertext = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);
        $hash = hash_hmac('sha256', $ciphertext . $iv, $key, true);

        return $iv . $hash . $ciphertext;
    }



    public static function getApikeyDk($id, $currency)
    {   
        $get = AccessProfiles::profilesCached()->where('id', $id)->first();
        $apikey = Cache::get($id.$currency);  

            if (!$apikey) {
                $explode = explode(',', $get->api_dk);
                if($currency === 'USD') {
                    $apikey = $explode[0];
                } elseif($currency === 'EUR') {
                    $apikey = $explode[1];
                } elseif($currency === 'CAD') { 
                    $apikey = $explode[2];
                } elseif($currency === 'TRY') { 
                    $apikey = $explode[3];
                } elseif($currency === 'TND') { 
                    $apikey = $explode[4];
                }
                Cache::put($id.$currency, $apikey, Carbon::now()->addMinutes(10));
            } 

        return $apikey;
    }

    public function startDemo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'apikey' => ['required', 'min:12', 'max:155'],
            'apikey_owner' => ['required', 'min:2', 'max:20'],
            'game' => ['required', 'min:3', 'max:100'],
            'currency' => ['required', 'min:2', 'max:7'],
        ]);

        if ($validator->stopOnFirstFailure()->fails()) {
            return response()->json(['status' => 400, 'error' => 'Validation of request form failed.', 'validation_messages' => $validator->errors(), 'request_ip' => $_SERVER['REMOTE_ADDR']])->setStatusCode(400);
        }

        $ip = $_SERVER['REMOTE_ADDR'];

        $selectParentApiSettings = GameoptionsParent::where('apikey_parent', $request->apikey)->first();

        /**
         * Check API Key credentials & authorization
         * @return array
         */
        if(!$selectParentApiSettings) {
            return response()->json([
                'status' => 401,
                'error' => 'Authorization error. Check if apikey and apikey_owner are matching.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        if($selectParentApiSettings->ownedBy !== $request->apikey_owner) {
            return response()->json([
                'status' => 401,
                'error' => 'Authorization error. Check if apikey and apikey_owner are matching.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }


        if($selectParentApiSettings->active === 0) {
            return response()->json([
                'status' => 401,
                'error' => 'Authorization error - your API key is disabled, contact support.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        if(!str_contains($selectParentApiSettings->allowed_ips, $ip)) {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '401', $ip.' is not in allowed IP-address list on your API settings. If you see this error and do not reconize IP, please contact support as your API access might have been compromised.', $request->fullUrl());
            return response()->json([
                'status' => 401,
                'error' => 'Not in allowed IP-address list on your API settings.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        $selectApiSettings = Gameoptions::where('apikey', $request->currency.'-'.$request->apikey)->first();

        /**
         * Check API Key credentials & authorization
         * @return array
         */
        if(!$selectApiSettings) {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '401', 'Sub currency profile not found, create sub-currency profile or contact support.', $request->fullUrl());
            return response()->json([
                'status' => 401,
                'error' => 'Sub currency profile not found, create sub-currency profile or contact support.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }


        /**
         * Get Profile access settings
         */
        $accessProfileSettings = AccessProfiles::profilesCached()->where('id', $selectParentApiSettings->access_profile)->first();
        if(!$accessProfileSettings) {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '404', 'Operator access profile not found, try a different currency or else contact our tech support immediately.', $request->fullUrl());

            return response()->json([
                'status' => 404,
                'error' => 'Operator access profile not found, try a different currency or else contact our tech support immediately.',
                'request_ip' => $ip
            ])->setStatusCode(404);
        }

        if($selectParentApiSettings->hourly_spare_demosessions < '1') {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '401', 'Hourly max. demo sessions reached - contact support to increase your demo hourly spare sessions. Current max. demo sessions per hour: '.$accessProfileSettings->max_hourly_demosessions, $request->fullUrl());
            return response()->json([
                'status' => 401,
                'error' => 'Hourly max. demo sessions reached - contact support to increase your demo hourly spare sessions. Current max. demo sessions per hour: '.$accessProfileSettings->max_hourly_demosessions,
                'request_ip' => $ip
            ])->setStatusCode(401);
        }


        $max_entries_sessions = $accessProfileSettings->max_entries_sessions;
        
        /**
         * Currency & checking if currency is enabled
         */
        $currency = strtoupper($request->currency);
        if(!str_contains($selectApiSettings->native_currency, $currency)) {
            return response()->json([
                'status' => 401,
                'error' => 'Currency not allowed.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        /**
         * Request, store demo session & serve the actual launcher
         */
        $generateDemoPlayerID = 'demo'.rand(1000000, 99999999999);
        $url = 'https://api.dk.games/v2:709:1fCdsFe/createGame?apikey='.self::getApikeyDk($selectParentApiSettings->access_profile, 'USD').'&userid='.$generateDemoPlayerID.'&game='.$request->game.'&mode=demo';
        $getDemo = Http::get($url);
        $decodeResult = json_decode($getDemo, true);

        if($getDemo->status() === 200) {
            $selectParentApiSettings->update([
                'hourly_spare_demosessions' => $selectParentApiSettings->hourly_spare_demosessions - 1,
            ]);
            $selectApiSettings->update([
                'demo_sessions' => $selectApiSettings->demo_sessions + 1,
            ]);

            $concat = $selectApiSettings->id.'@'.$max_entries_sessions.'@'.$generateDemoPlayerID.'@'.time().'@'.$decodeResult['url'];
            $tokenEncrypt = bin2hex(self::encryptCasinoToken(($concat), 'doNotRandomizedPassword'));

            $sessionLog = DemoSessions::insert([
                'uid' =>  Uuid::generate(4),
                'casino_id' => $selectApiSettings->id,
                'game' => $request->game,
                'session_id' => $tokenEncrypt,
                'ownedBy' => $selectApiSettings->ownedBy,
                'player_id' => $generateDemoPlayerID,
                'player_meta' => '[]',
                'player_ip' => 0,
                'currency' => $currency,
                'branded' => $accessProfileSettings->branded ?? 0,
                'visited' => 0,
                'active' => 1,
                'request_ip' => $ip,
                'updated_at' => now(),
                'created_at' => now()
            ]);

            if($accessProfileSettings === 0) {
                $launchurl = $decodeResult['url'];
            } else {
                $launchurl = $decodeResult['url'];
                $launchurl = 'https://launcher.betboi.io?key='.$tokenEncrypt.'&mode=demo';
            } 

            return response()->json([
                'status' => 200,
                'url' => $launchurl,
                'request_ip' => $ip
            ])->setStatusCode(200);

        } else {
                $error = $decodeResult['error'];
            if($selectParentApiSettings->return_log !== 1) {
                $url = false;
                $error = 'Error retrieving game link from main api - contact support immediately if persists';
            }

            return response()->json([
                'status' => 500,
                'error' => $error,
                'url' => $url,
                'request_ip' => $ip
            ])->setStatusCode(500);
        }
    }

    public function startSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'apikey' => ['required', 'min:20', 'max:255'],
            'apikey_owner' => ['required', 'min:2', 'max:20'],
            'game' => ['required', 'min:3', 'max:100'],
            'playerid' => ['required', 'min:3', 'max:100', 'regex:/^[^(\|\]`!%^&=};:?><’)]*$/'],
            'extra_currency' => ['min:2', 'max:7'],
            'currency' => ['required', 'min:2', 'max:7'],
        ]);



        if ($validator->stopOnFirstFailure()->fails()) {
            return response()->json(['status' => 400, 'error' => 'Validation of request form failed.', 'validation_messages' => $validator->errors(), 'request_ip' => $_SERVER['REMOTE_ADDR']])->setStatusCode(400);
        }

        $ip = $_SERVER['REMOTE_ADDR'];


        if(str_contains($request->playerid, '-') || str_contains($request->playerid, '.')) {
            return response()->json([
                'status' => 400,
                'error' => 'You are using not allowed characters in player id like: . or -',
                'request_ip' => $ip,
                'allowed_ips' => $selectApiSettings->allowed_ips
            ])->setStatusCode(401);
        }

        $selectParentApiSettings = GameoptionsParent::where('apikey_parent', $request->apikey)->first();

        /**
         * Check API Key credentials & authorization
         * @return array
         */
        if(!$selectParentApiSettings) {
            return response()->json([
                'status' => 401,
                'error' => 'Authorization error. Check if apikey and apikey_owner are matching.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        if($selectParentApiSettings->ownedBy !== $request->apikey_owner) {
            return response()->json([
                'status' => 401,
                'error' => 'Authorization error. Check if apikey and apikey_owner are matching.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        if($selectParentApiSettings->active === 0) {
            return response()->json([
                'status' => 401,
                'error' => 'Authorization error - your API key is disabled, contact support.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        if(!str_contains($selectParentApiSettings->allowed_ips, $ip)) {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '401', $ip.' is not in allowed IP-address list on your API settings. If you see this error and do not reconize IP, please contact support as your API access might have been compromised.', $request->fullUrl());

            return response()->json([
                'status' => 401,
                'error' => 'Not in allowed IP-address list on your API settings.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }


        $selectApiSettings = Gameoptions::where('apikey', $request->currency.'-'.$request->apikey)->first();

        /**
         * Check sub profile (mainly per currency)
         * @return array
         */
        if(!$selectApiSettings) {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '401', 'Sub currency profile not found, create sub-currency profile or contact support.', $request->fullUrl());
            return response()->json([
                'status' => 401,
                'error' => 'Sub currency profile not found, create sub-currency profile or contact support.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        /**
         * Get Profile access settings
         */
        $accessProfileSettings = AccessProfiles::profilesCached()->where('id', $selectParentApiSettings->access_profile)->first();
        if(!$accessProfileSettings) {
            CreateSessionErrors::insertCreateSessionError($request->apikey, $selectParentApiSettings->ownedBy, '404', 'Operator access profile not found, try a different currency or else contact our tech support immediately.', $request->fullUrl());

            return response()->json([
                'status' => 404,
                'error' => 'Operator access profile not found, try a different currency or else contact our tech support immediately.',
                'request_ip' => $ip
            ])->setStatusCode(404);
        }

        $max_entries_sessions = $accessProfileSettings->max_entries_sessions;
        /**
         * Currency & checking if currency is enabled
         */
        $currency = strtoupper($request->currency);
        if(!str_contains($selectApiSettings->native_currency, $currency)) {
            return response()->json([
                'status' => 401,
                'error' => 'Currency not allowed.',
                'request_ip' => $ip
            ])->setStatusCode(401);
        }

        $extra_currency = strtoupper($request->extra_currency);
        if($request->extra_currency === NULL) {
            $extra_currency = 0;
        }

        /**
         * Request, store real session & serve the actual launcher
         */
        $currency = strtoupper($request->currency);
        $playerID = $request->playerid;
        $concatSessionUser = $selectApiSettings->id.'.'.$currency.'.'.$playerID.'-'.$currency;
        $url = 'https://api.dk.games/v2:709:1fCdsFe/createGame?apikey='.self::getApikeyDk($selectParentApiSettings->access_profile, $currency).'&userid='.$concatSessionUser.'&game='.$request->game.'&mode=real';
        $createSession = Http::get($url);
        $decodeResult = json_decode($createSession, true);

        if($createSession->status() === 200) {
            $selectApiSettings->update([
                'real_sessions' => $selectApiSettings->real_sessions + 1,
            ]);
            /**
             * Invalidated other sessions
             */
            $findUnvisited = RegularSessions::where('casino_id', $selectApiSettings->id)->where('player_id', $playerID)->first();
            if($findUnvisited) {
                $sessionLog = RegularSessions::where('casino_id', $selectApiSettings->id)->where('player_id', $playerID)->update([
                    'active' => 0,
                ]);
            }

            $concat = $selectApiSettings->id.'@'.$max_entries_sessions.'@'.$playerID.'@'.time().'@'.$decodeResult['url'];
            $tokenEncrypt = bin2hex(self::encryptCasinoToken(($concat), 'doNotRandomizedPassword'));

            $sessionLog = RegularSessions::insert([
                'uid' =>  Uuid::generate(4),
                'casino_id' => $selectApiSettings->id,
                'game' => $request->game,
                'session_id' => $tokenEncrypt,
                'player_id' => $playerID,
                'currency' => $currency,
                'ownedBy' => $selectApiSettings->ownedBy,
                'extra_currency' => $extra_currency,
                'player_meta' => '[]',
                'player_ip' => 0,
                'branded' => $accessProfileSettings->branded ?? 0,
                'visited' => 0,
                'request_ip' => $ip,
                'active' => 1,
                'updated_at' => now(),
                'created_at' => now()
            ]);
            if($accessProfileSettings === 0) {
                $launchurl = $decodeResult['url'];
            } else {
                $launchurl = 'https://launcher.betboi.io?key='.$tokenEncrypt.'&mode=regular';
            } 

            return response()->json([
                'status' => 200,
                'orig' => $decodeResult['url'],
                'url' => $launchurl,
                'request_ip' => $ip
            ])->setStatusCode(200);

        } else {
                $error = $url;
            if($selectParentApiSettings->return_log !== 1) {
                $url = false;
                $error = 'Error retrieving game link from main api - contact support immediately if persists';
            }

            return response()->json([
                'status' => 500,
                'error' => $error,
                'url' => $url,
                'request_ip' => $ip
            ])->setStatusCode(500);
        }
    }


}


