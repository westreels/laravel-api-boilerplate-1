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
use App\Models\Slotlayer\GameoptionsParent;
use App\Models\Slotlayer\DemoSessions;
use App\Models\Slotlayer\CurrencyPrices;
use App\Models\Slotlayer\RegularSessions;
use App\Models\Slotlayer\AccessProfiles;
use App\Models\Slotlayer\GametransactionsLive;
use App\Models\Slotlayer\CallbackErrors;
use \Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Dingo\Api\Routing\Helpers;
use Dingo\Api\Http\Response;
use Illuminate\Support\Facades\Crypt;

class CallbackController extends \App\Http\Controllers\Controller
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

   /**
     * @param balance return to dk slotmachine
     * @return \Illuminate\Http\JsonResponse
     */
    public function balanceDkTunnel(Request $request)
    {   
            if(!env('APP_ENV') === 'staging') {
                if($_SERVER['REMOTE_ADDR'] !== '51.89.65.49') {
                    Log::warning('Callback from weird IP:'.$_SERVER['REMOTE_ADDR']);
                    return;
                }
            }

            $getUsername = $request['playerid'];
            $explodeUsername = explode('.', $getUsername);
            //$getSession = Players::where('player_id', $explodeUsername[1])->where('casino_id', $explodeUsername[0])->first();

            $currency = $explodeUsername[1];
            $extra_currency = $request['currency'];
            $playerId = $explodeUsername[2];
            $findoperator = Gameoptions::where('id', $explodeUsername[0])->first();
            $findoperatorParent = GameoptionsParent::where('apikey_parent', $findoperator->parent_key)->first();

            $baseurl = $findoperatorParent->callbackurl;
            $prefix = $findoperatorParent->slots_prefix;
            $url = $baseurl.$prefix.'/balance?currency='.$currency.'&extra_currency='.$extra_currency.'&playerid='.$playerId;
            $curlcatalog = curl_init();
                curl_setopt_array($curlcatalog, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 1,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_POST => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
              ),
            ));

            $responsecurl = curl_exec($curlcatalog);
            $http_code = curl_getinfo($curlcatalog, CURLINFO_HTTP_CODE);
            curl_close($curlcatalog);
            $responsecurl = json_decode($responsecurl, true); 


            if($http_code !== 200) {
                CallbackErrors::insertCallbackError($findoperator->parent_key, $findoperator->ownedBy, '500', 'Callback Error, please see request to see full URL. Http code: '.$http_code, $url);
            }

            if(isset($responsecurl['result'])) {

            return response()->json([
                'result' => ([
                    'balance' => floatval($responsecurl['result']['balance']),
                    'freegames' => 0,
                ]),
                'id' => 0,
                'jsonrpc' => '2.0'
            ])->setStatusCode(200);
            } else {
                CallbackErrors::insertCallbackError($findoperator->parent_key, $findoperator->ownedBy, '500', 'No array with balance response while status code still was 200.', $url);
            }
        }
   /**
     * @param result slotmachine
     * @return \Illuminate\Http\JsonResponse
     */
    public function result(Request $request)
    {
            if(!env('APP_ENV') === 'staging') {
                if($_SERVER['REMOTE_ADDR'] !== '51.89.65.49') {
                    Log::warning('Callback from weird IP:'.$_SERVER['REMOTE_ADDR']);
                    return;
                }
            }

            $getUsername = $request['playerid'];
            $explodeUsername = explode('.', $getUsername);
            //$getSession = Players::where('player_id', $explodeUsername[1])->where('casino_id', $explodeUsername[0])->first();

            $currency = $explodeUsername[1];
            $extra_currency = $request['currency'];
            $playerId = $explodeUsername[2];
            $roundId = $request['roundid'];
            $final = $request['final'];
            $findoperator = Gameoptions::where('id', $explodeUsername[0])->first();
            $withdraw = intval($request['bet']);
            $deposit = intval($request['win']);

            $findoperatorParent = GameoptionsParent::where('apikey_parent', $findoperator->parent_key)->first();

            if($findoperator->native_currency !== "USD") {
                $exchange = CurrencyPrices::cachedPrices($findoperator->native_currency);
            } else { 
                $exchange = 1.00;
            }
            $gameId = $request['gameid'];
            $gameProvider = $request['gameprovider'];

            $transactionRef = $roundId.rand('100', '9999');
            $totalBet = intval($request['bet']);
            $totalWin = intval($request['win']);

            $OperatorTransactions = GametransactionsLive::create(['casinoid' => $findoperator->id, 'currency' => $findoperator->native_currency, 'player' => $playerId, 'ownedBy' => $findoperator->ownedBy, 'bet' => $withdraw, 'win' => $deposit, 'access_profile' => $findoperatorParent->access_profile, 'gameid' => $gameId, 'txid' => $transactionRef, 'roundid' => $roundId, 'usd_exchange' => $exchange, 'callback_state' => '1', 'type' => 'external_game', 'rawdata' => '[]']);
            $baseurl = $findoperatorParent->callbackurl;
            $prefix = $findoperatorParent->slots_prefix;
            $verifySign = sha1($findoperatorParent->apikey_parent.'^'.$findoperatorParent->operator_secret.'^'.$roundId);

            if($final === "1") {
                    //$totalTxs = GametransactionsLive::where('roundid', '=', $roundId)->where('player', '=', $playerId)->get();
                    //$totalWin = $totalTxs->sum('win');
                    //$totalBet = $totalTxs->sum('bet');
                $url = $baseurl.$prefix.'/bet?currency='.$currency.'&extra_currency='.$extra_currency.'&gameprovider='.$gameProvider.'&gameid='.$gameId.'&roundid='.$roundId.'&playerid='.$playerId.'&bet='.$withdraw.'&win='.$deposit.'&bonusmode=0&final='.$final.'&totalBet='.$totalBet.'&totalWin='.$totalWin.'&sign='.$verifySign;
                    //$processGgr = GametransactionsLive::processGgr($request['gameid'], $findoperator->id, $totalWin, $totalBet);        
                
                } else {
                $url = $baseurl.$prefix.'/bet?currency='.$currency.'&extra_currency='.$extra_currency.'&gameprovider='.$gameProvider.'&gameid='.$gameId.'&roundid='.$roundId.'&playerid='.$playerId.'&bet='.$withdraw.'&win='.$deposit.'&bonusmode=0&final='.$final.'&sign='.$verifySign;
                }

            $curlcatalog = curl_init();
                curl_setopt_array($curlcatalog, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 1,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_POST => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
              ),
            ));


            $responsecurl = curl_exec($curlcatalog);
            curl_close($curlcatalog);

            $responsecurl = json_decode($responsecurl, true); 

            if(isset($responsecurl['result'])) {
            return response()->json([
                'result' => ([
                    'balance' => floatval($responsecurl['result']['balance']),
                    'freegames' => 0,
                ]),
                'id' => 0,
                'jsonrpc' => '2.0'
            ])->setStatusCode(200);
                } else {
                $error = array('error' => 'Error callback, main apikey: '.$findoperatorParent->apikey_parent.', sub-operator id '.$findoperator->id.', operator callback url '.$url, 'txid' => $transactionRef, 'player' => $playerId);
                CallbackErrors::insertCallbackError($findoperator->parent_key, $findoperator->ownedBy, '500', 'Error callback, main apikey: '.$findoperatorParent->apikey_parent.', sub-operator id '.$findoperator->id.', operator callback url '.$url.', txid '.$transactionRef.', player '.$playerId, $url);


                $OperatorTransactions->update(['callback_state' => 0, 'rawdata' => json_encode($error)]);
            }

    }

   /**
     * @param result slotmachine
     * @return \Illuminate\Http\JsonResponse
     */
    public function testBalanceCallback(Request $request)
    {
            return response()->json([
                'result' => ([
                    'balance' => 100,
                    'freegames' => 0,
                ]),
                'id' => 0,
                'jsonrpc' => '2.0'
            ])->setStatusCode(200);
    }



}


