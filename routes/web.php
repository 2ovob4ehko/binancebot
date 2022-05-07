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
    Route::get('/market/{id}', [\App\Http\Controllers\MainController::class, 'showMarket']);
    Route::post('/market', [\App\Http\Controllers\MainController::class, 'saveMarket']);
    Route::delete('/market/{id}', [\App\Http\Controllers\MainController::class, 'deleteMarket']);
    Route::post('/market_analysis/{id}', [\App\Http\Controllers\MainController::class, 'analysisMarket']);
});

Auth::routes([
    'register' => false, // Registration Routes...
    'reset' => true, // Password Reset Routes...
    'verify' => true,
]);

Route::get('/test', function(){
//    $candles = \App\Http\Controllers\MainController::multilimitQueryS('WTCUSDT','1d',1000);
//    $analysis = new Analysis();
//    $closed = array_map(function($el){return $el[4];}, $candles);
//    $rsi = $analysis->rsi($closed,14);
//    echo '<pre>';
//    var_dump($closed);
//    echo '</pre>';
});
//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
