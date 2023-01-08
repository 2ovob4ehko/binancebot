<?php

use App\Helpers\FuturesTrading;
use App\Helpers\Trading;
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
        if((time() - $restart_time) / 60 > 5){ //every 10 minutes
            $restart_time = time();
            $client = new WebSocket\Client("wss://stream.binance.com:9443/ws",[
                'timeout' => 600
            ]);
            $new_markets = Trading::getMarketsForTrade();
            $subs = [];
            foreach ($new_markets as $key => $market){
                if(array_key_exists($key,$markets)){
                    $new_markets[$key] = $markets[$key];
                }
                $subs[] = mb_strtolower($market['name']).'@kline_'.$market['settings']['candle'];
            }
            $markets = $new_markets;
            $this->info(date('[Y-m-d H:i:s]').' subs: '.json_encode($subs));
            $client->send('{
              "method": "SUBSCRIBE",
              "params": '.json_encode($subs).',
              "id": 1
            }');
        }
        try {
            if($subs) {
                $message = $client->receive();
                $data = json_decode($message, true);
                if ($data && array_key_exists('s', $data)) {
                    $proc_start = microtime(true);
                    $selected_markets = array_filter($markets,function($market) use ($data){
                        return $market['name'] == $data['s'];
                    });
                    foreach ($selected_markets as $key => $selected){
                        if ($data['e'] == 'kline') {
                            $trading = new Trading($markets[$key], $data['k'], $this);
                            $markets[$key] = $trading->addNewCandle();
                        }
                    }
                    $proc_time = (microtime(true) - $proc_start)*1000;
                    if($proc_time > 1000) $this->info(date('[Y-m-d H:i:s]').' proc time: ' . $proc_time);
                }else{
                    $this->info(date('[Y-m-d H:i:s]').' message: ' . $message);
                }
            }
        } catch (\WebSocket\ConnectionException $e) {
            $this->info('error: '.$e);
        }
    }
    $client->close();
})->purpose('Binance trading by Websocket event');

Artisan::command('futures_trade', function(){
    $restart_time = 0;
    $markets = [];
    while (true) {
        if((time() - $restart_time) / 60 > 10){ //every 10 minutes
            $restart_time = time();
            $client = new WebSocket\Client("wss://fstream.binance.com/ws",[
                'timeout' => 600
            ]);
            $new_markets = FuturesTrading::getMarketsForTrade();
            $subs = [];
            foreach ($new_markets as $key => $market){
                if(array_key_exists($key,$markets)){
                    $new_markets[$key] = $markets[$key];
                }
                $subs[] = mb_strtolower($market['name']).'@ticker';
            }
            $markets = $new_markets;
            $this->info(date('[Y-m-d H:i:s]').' futures subs: '.json_encode($subs));
            $client->send('{
              "method": "SUBSCRIBE",
              "params": '.json_encode($subs).',
              "id": 1
            }');
        }
        try {
            if($subs) {
                $message = $client->receive();
                $data = json_decode($message, true);
                if ($data && array_key_exists('s', $data)) {
                    $proc_start = microtime(true);
                    $selected_markets = array_filter($markets,function($market) use ($data){
                        return $market['name'] == $data['s'];
                    });
                    foreach ($selected_markets as $key => $selected){
                        $trading = new FuturesTrading($markets[$key], $data, $this);
                        $markets[$key] = $trading->addNewPrice();
                    }
                    $proc_time = (microtime(true) - $proc_start)*1000;
                    if($proc_time > 1000) $this->info(date('[Y-m-d H:i:s]').' proc time: ' . $proc_time);
                }else{
                    $this->info(date('[Y-m-d H:i:s]').' message: ' . $message);
                }
            }
        } catch (\WebSocket\ConnectionException $e) {
            $this->info('error: '.$e);
        }
    }
    $client->close();
})->purpose('Binance futures trading by Websocket event');
