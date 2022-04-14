<?php

use Dingo\Api\Routing\Router;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


/** @var \Dingo\Api\Routing\Router $api */
$api = app('Dingo\Api\Routing\Router');
$api->version('v1', ['middleware' => ['api']], function (Router $api) {
    
    $api->group(['prefix' => 'game', ['middleware' => 'api.throttle', 'limit' => 100, 'expires' => 2]], function (Router $api) {
        $api->get('/createDemo', 'App\Http\Controllers\Slotlayer\AggregationController@startDemo');
        $api->get('/createSession', 'App\Http\Controllers\Slotlayer\AggregationController@startSession');

    });

    /*
     *  External Callbacks
     */
    $api->group(['prefix' => 'calls'], function (Router $api) {
        $api->any('/aggregation/game/balance', 'App\Http\Controllers\Slotlayer\CallbackController@balanceDkTunnel');
        $api->any('/aggregation/game/bet', 'App\Http\Controllers\Slotlayer\CallbackController@result');
    });

    /*
     * Authentication
     */
    $api->group(['prefix' => 'auth'], function (Router $api) {
        $api->group(['prefix' => 'jwt'], function (Router $api) {
            $api->get('/token', 'App\Http\Controllers\Auth\AuthController@token');
        });
    });
 
    $api->group(['prefix' => 'staging'], function (Router $api) {
        $api->get('/testRetrieveAccessProfiles', 'App\Models\Slotlayer\AccessProfiles@testRetrieve');
        $api->get('/testBalanceCallback/game/bet', 'App\Http\Controllers\Slotlayer\CallbackController@testBalanceCallback');
        $api->get('/testBalanceCallback/game/balance', 'App\Http\Controllers\Slotlayer\CallbackController@testBalanceCallback');
    });

    /*
     * Authenticated routes
     */
    $api->group(['middleware' => ['api.auth']], function (Router $api) {
        /*
         * Authentication
         */
        $api->group(['prefix' => 'auth'], function (Router $api) {
            $api->group(['prefix' => 'jwt'], function (Router $api) {
                $api->get('/refresh', 'App\Http\Controllers\Auth\AuthController@refresh');
                $api->delete('/token', 'App\Http\Controllers\Auth\AuthController@logout');
            });

            $api->get('/me', 'App\Http\Controllers\Auth\AuthController@getUser');
        });

        /*
         * Users
         */
        $api->group(['prefix' => 'users', 'middleware' => 'check_role:admin'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\UserController@getAll');
            $api->get('/{uuid}', 'App\Http\Controllers\UserController@get');
            $api->post('/', 'App\Http\Controllers\UserController@post');
            $api->put('/{uuid}', 'App\Http\Controllers\UserController@put');
            $api->patch('/{uuid}', 'App\Http\Controllers\UserController@patch');
            $api->delete('/{uuid}', 'App\Http\Controllers\UserController@delete');
        });

        /*
         * Roles
         */
        $api->group(['prefix' => 'roles'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\RoleController@getAll');
        });
    });
});
