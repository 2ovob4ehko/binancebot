<?php

use App\Helpers\Analysis;
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
    Route::get('/', [\App\Http\Controllers\HomeController::class, 'index']);
    Route::get('/market/{id}', [\App\Http\Controllers\MarketController::class, 'show']);
    Route::post('/market', [\App\Http\Controllers\MarketController::class, 'save']);
    Route::delete('/market/{id}', [\App\Http\Controllers\MarketController::class, 'delete']);
    Route::post('/market_analysis/{id}', [\App\Http\Controllers\MarketController::class, 'analysis']);
    Route::get('/uploadCSVFromBinance/{market}', [\App\Http\Controllers\MarketController::class, 'uploadCSVFromBinance']);
});

Auth::routes([
    'register' => true, // Registration Routes...
    'reset' => true, // Password Reset Routes...
    'verify' => true,
]);

Route::get('/test', function(){

});
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
