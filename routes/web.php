<?php

use App\Http\Controllers\FutureMarketController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\SettingController;
use App\Models\User;
use Binance\API;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['auth']], function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/market/{id}', [MarketController::class, 'show']);
    Route::post('/market', [MarketController::class, 'save']);
    Route::delete('/market/{id}', [MarketController::class, 'delete']);
    Route::post('/market_analysis/{id}', [MarketController::class, 'analysis']);
    Route::get('/uploadCSVFromBinance/{market}', [MarketController::class,'uploadCSVFromBinance']);
    Route::get('/settings', [SettingController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingController::class, 'save']);
    Route::get('/trade_list', [MarketController::class, 'tradeList'])->name('trade_list');
    Route::get('/futures_list', [FutureMarketController::class, 'index'])->name('futures_list');
    Route::get('/futures_market/{id}', [FutureMarketController::class, 'show']);
    Route::post('/futures_market', [FutureMarketController::class, 'save']);
});

Auth::routes([
    'register' => false, // Registration Routes...
    'reset' => true, // Password Reset Routes...
    'verify' => true,
]);

Route::get('/test', function(){
//    $user = User::find(2);
//    $api = new App\BinanceSDK\BinanceSDK(
//        $user->setting('api_key')->value ?? '',
//        $user->setting('secret_key')->value ?? ''
//    );
    // $api->commissionFee('BTCUAH')[0]['takerCommission'];
    // array_filter($api->account()['balances'],function($item){return $item['asset'] === 'BTC';});
    // $api->marketQuoteBuyTest('BTCUSDT',10); //marketQuoteBuy
    // $api->marketSellTest('BTCUSDT',0.0004); //marketSell
//     $api->sell('BTCUAH','0.00016','1100000') // продаж бітка по ціні
//    try{
//        $res = $api->orders('BTCUAH');
//        echo '<pre>';
//        var_dump($res);
//        echo '</pre>';
//    }catch (Exception $e){
//        echo '<pre>';
//        var_dump($e->getMessage());
//        echo '</pre>';
//    }
//    echo '<pre>';
//    var_dump($api->lastRequest);
//    echo '</pre>';
});
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
