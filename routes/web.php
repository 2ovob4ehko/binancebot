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
    Route::get('/market/{id}', [MarketController::class, 'show'])->name('market');
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

});
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
