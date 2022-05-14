<?php

use App\Http\Controllers\TradeController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('trade', function(){
    $markets = TradeController::getMarketsForTrade();
    $client = new WebSocket\Client("wss://stream.binance.com:9443/ws",[
        'timeout' => 600
    ]);
    $subs = [];
    foreach ($markets as $key => $market){
        $subs[] = mb_strtolower($key).'@trade';
    }
    $client->send('{
      "method": "SUBSCRIBE",
      "params": '.json_encode($subs).',
      "id": 1
    }');
    while (true) {
        try {
//            $client->pong();
            $message = $client->receive();
            $data = json_decode($message);
            if(array_key_exists('s',$data)){
                TradeController::addNewPrice($markets[$data['s']],$data);
            }
            $this->info('message: '.$message);
        } catch (\WebSocket\ConnectionException $e) {
            $this->info('error: '.$e);
        }
    }
    $client->close();
})->purpose('Binance trading by Websocket event');
