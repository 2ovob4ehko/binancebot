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
    $restart_time = 0;
    $markets = [];
    while (true) {
        if((time() - $restart_time) / 60 > 10){
            $restart_time = time();
            $client = new WebSocket\Client("wss://stream.binance.com:9443/ws",[
                'timeout' => 600
            ]);
            $new_markets = TradeController::getMarketsForTrade();
            $subs = [];
            foreach ($new_markets as $key => $market){
                if(array_key_exists($key,$markets)){
                    $new_markets[$key] = $markets[$key];
                }
//        $subs[] = mb_strtolower($key).'@trade';
                $subs[] = mb_strtolower($key).'@kline_'.$market['settings']['candle'];
            }
            $markets = $new_markets;
            $this->info('subs: '.json_encode($subs));
            $client->send('{
              "method": "SUBSCRIBE",
              "params": '.json_encode($subs).',
              "id": 1
            }');
        }
        try {
//            $client->pong();
            if($subs) {
                $message = $client->receive();
                $data = json_decode($message, true);
                if ($data && array_key_exists('s', $data)) {
//                if($data['e'] == 'trade'){
//                    $markets[$data['s']] = TradeController::addNewPrice($markets[$data['s']],$data);
//                }
                    if ($data['e'] == 'kline') {
                        $markets[$data['s']] = TradeController::addNewCandle($markets[$data['s']], $data['k']);
                    }
                }
                $this->info('message: ' . $message);
            }
        } catch (\WebSocket\ConnectionException $e) {
            $this->info('error: '.$e);
        }
    }
    $client->close();
})->purpose('Binance trading by Websocket event');
