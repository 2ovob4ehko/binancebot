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
    // $api->sell('BTCUAH','0.00016','1100000') // продаж бітка по ціні
//    try{
////        $api->marginTypeFuture('BTCUSDT',true);
//        echo '<pre>';
//        var_dump($api->accountFuture()['positions']['BTCUSDT']);
//        echo '</pre>';
//    }catch (Exception $e){
//        echo '<pre>';
//        var_dump($e->getMessage());
//        echo '</pre>';
//    }
//    echo '<pre>';
//    var_dump($api->lastRequest);
//    echo '</pre>';

    $commission = 0.001;
    $close = 1320.0*1.003;
    $old_price = 1000.0;
    $profit_limit = 0.001;
    $status = 'bought';
    $balance = 0.07568181;

    $rsi_buy_rule = true;
    $rsi_sell_rule = false;

    $stoch_buy_rule = true;
    $stoch_sell_rule = true;

    $is_profit = !(floatval($profit_limit) == 0.0) && $close > $old_price * (1 + floatval($profit_limit));

    if($status == 'deposit' && $rsi_buy_rule && $stoch_buy_rule){
        $status = 'bought';
        $balance = 100; // stable set amount of quote for buying
        // TODO: зробити скорочення до 0.000000
        $balance = $close ? $balance / $close : $balance;
        $balance = floor($balance * (1 - $commission) * 10**8) / 10**8;
    }elseif($status == 'bought' && (($rsi_sell_rule && $stoch_sell_rule) || $is_profit)){
        $status = 'deposit';
        // TODO: зробити скорочення до 0.00
        $balance = $balance * $close;
        $balance = floor($balance * (1 - $commission) * 10**8) / 10**8;
    }
echo '<pre>';
var_dump($balance);
echo '</pre>';
});
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
