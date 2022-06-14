<?php

namespace App\Http\Controllers;


use App\Helpers\Analysis;
use App\Helpers\Intervals;
use App\Models\Market;
use App\Models\Simulation;
use Binance\API;

class TradeController extends Controller
{
    public static function getMarketsForTrade()
    {
        $output = [];
        $markets = Market::where('is_online',1)->orWhere('is_trade',1)->get();
        if($markets->count() > 0){
            foreach ($markets as $market){
                $settings = $market->settings;
                $candles = MarketController::multilimitQuery($market->name,$settings['candle'],50);

                $user = $market->user;
                $api_key = $user->setting('api_key')->value ?? '';
                $secret_key = $user->setting('secret_key')->value ?? '';

                $output[$market->id] = [
                    'id' => $market->id,
                    'name' => $market->name,
                    'settings' => $market->settings,
                    'data' => $candles,
                    'mark' => false,
                    'is_trade' => $market->is_trade,
                    'api' => new API($api_key, $secret_key)
                ];
            }
        }
        return $output;
    }
/*
 * trade = {
  "e": "trade",     // Event type
  "E": 123456789,   // Event time
  "s": "BNBBTC",    // Symbol
  "t": 12345,       // Trade ID
  "p": "0.001",     // Price
  "q": "100",       // Quantity
  "b": 88,          // Buyer order ID
  "a": 50,          // Seller order ID
  "T": 123456785,   // Trade time
  "m": true,        // Is the buyer the market maker?
  "M": true         // Ignore
}
 */
    public static function addNewPrice($market,$trade)
    {
        $settings = $market['settings'];
        $candle_time = Intervals::ms()[$settings['candle']];
        $data = $market['data'];
        $last_index = count($data)-1;
        $last_closed_index = count($data)-2;
        $new_candle = false;

        $data[$last_index][6] = intval($trade['T']);
        $data[$last_index][4] = $trade['p'];
        $data[$last_index][2] = floatval($trade['p']) < floatval($data[$last_index][2]) ? $data[$last_index][2] : $trade['p'];
        $data[$last_index][3] = floatval($trade['p']) > floatval($data[$last_index][3]) ? $data[$last_index][3] : $trade['p'];

        if((intval($trade['T']) - intval($data[$last_index][0])) >= $candle_time){
            $data[$last_index+1] = [intval($trade['T']),$trade['p'],$trade['p'],$trade['p'],$trade['p'],0,intval($trade['T'])];
            array_splice($data, 0, 1);
            $new_candle = true;
        }
//        TODO:
//        1а) Спробувати провести аналіз свічок, не дивлячись на те, чи свічка закрилася чи ні
//        1б) Провести аналіз свічок, якщо свічка закрилася.
        $analysis = new Analysis();
        $closed = array_map(function($el){return floatval($el[4]);}, $data);
        $rsi = $analysis->rsi($closed,$settings['rsi_period']);
        $is_stoch = false;
        if(!empty($settings['stoch_rsi_period'])){
            $stoch_rsi = $analysis->stoch_rsi($rsi,$settings['rsi_period'],$settings['stoch_rsi_period']);
            $is_stoch = true;
        }else{
            $stoch_rsi = [];
        }
//        2) Робити аналіз покупки чи продажу. Якщо є покупка чи продаж, але не створилася нова свічка, тоді треба зберегти $mark в $market. Якщо створилася нова свічка то скинути $market['mark'] на false, і записати в базу теперішній $mark.
        $balance = array_key_exists('balance',$market) ? $market['balance'] : floatval($settings['start_balance']);
        $old_balance = array_key_exists('old_balance',$market) ? $market['old_balance'] : floatval($settings['start_balance']);
        $status = array_key_exists('status',$market) ? $market['status'] : 'deposit';
        $i = $last_index;
        if($is_stoch){
            $stoch_rsi_logic = '';
            $sr = $stoch_rsi['stoch_rsi'];
            $ssr = $stoch_rsi['sma_stoch_rsi'];
            if(array_key_exists($i-1,$ssr)){
                if($sr[$i] > 80 && $ssr[$i] > 80){
                    if($sr[$i-1] >= $sr[$i] && $ssr[$i-1] >= $ssr[$i] && $sr[$i-1] >= $ssr[$i-1] && $sr[$i] <= $ssr[$i]){
                        $stoch_rsi_logic = 'down';
                    }
                }elseif($sr[$i] < 20 && $ssr[$i] < 20){
                    if($sr[$i-1] <= $sr[$i] && $ssr[$i-1] <= $ssr[$i] && $sr[$i-1] <= $ssr[$i-1] && $sr[$i] >= $ssr[$i]){
                        $stoch_rsi_logic = 'up';
                    }
                }
            }
        }
        $close = $trade['p'];

        if($status == 'deposit' && $rsi[$i] <= $settings['rsi_min'] &&
            ($is_stoch ? $stoch_rsi_logic === 'up' : true)){
            $status = 'bought';
            $old_balance = $balance;
            // TODO: зробити скорочення до 0.000000
            $balance = $close ? floor($balance / $close * 1000000000) / 1000000000 : $balance;
            Simulation::create([
                'market_id' => $market['id'],
                'action' => 'buy',
                'value' => $old_balance,
                'result' => $balance,
                'price' => $close,
                'rsi' => $rsi[$i],
                'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                'time' => date("Y-m-d H:i:s",intval($trade['T'])/1000)
            ]);
            $market['mark'] = 'buy';
        }elseif($status == 'bought'){
            $is_profit = floatval($settings['profit_limit']) == 0.0 ? false : $balance * $close > $old_balance * (1 + floatval($settings['profit_limit']));
            if((intval($settings['rsi_max']) > 0 && $rsi[$i] >= $settings['rsi_max'] &&
                    ($is_stoch ? $stoch_rsi_logic === 'down' : true)) || $is_profit){
                $status = 'deposit';
                $old_balance = $balance;
                // TODO: зробити скорочення до 0.00
                $balance = floor($balance * $close * 100) / 100;
                Simulation::create([
                    'market_id' => $market['id'],
                    'action' => 'sell',
                    'value' => $old_balance,
                    'result' => $balance,
                    'price' => $close,
                    'rsi' => $rsi[$i],
                    'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                    'time' => date("Y-m-d H:i:s",intval($trade['T'])/1000)
                ]);
                $market['mark'] = 'sell';
            }
        }
        $market['status'] = $status;
        $market['balance'] = $balance;
        $market['old_balance'] = $old_balance;
//        ---------------------------------------

        if($new_candle){
            $mark = $market['mark'];
            $market['mark'] = false;
            $market_db = Market::find($market['id']);
            $market_data = $market_db->data ?? [];
            array_push($market_data,['c' => $data[$last_closed_index], 'm' => $mark]);
            $market_rsi = $market_db->rsi ?? [];
            array_push($market_rsi,$rsi[$last_closed_index]);
            $market_stoch_rsi = $market_db->stoch_rsi ?? ['stoch_rsi' => [], 'sma_stoch_rsi' => []];
            if(!empty($stoch_rsi)) array_push($market_stoch_rsi['stoch_rsi'],$stoch_rsi['stoch_rsi'][$last_closed_index]);
            if(!empty($stoch_rsi)) array_push($market_stoch_rsi['sma_stoch_rsi'],$stoch_rsi['sma_stoch_rsi'][$last_closed_index]);
            $market_db->update([
                'data' => $market_data,
                'rsi' => $market_rsi,
                'stoch_rsi' =>$market_stoch_rsi
            ]);
        }
        $market['data'] = $data;
        return $market;
    }

    public static function addNewCandle($market,$trade,$console)
    {
        $settings = $market['settings'];
        if($settings['candle'] !== $trade['i']) return $market;
        $data = $market['data'];
        $last_index = count($data)-1;
        $last_closed_index = count($data)-2;
        $commission = array_key_exists('commission', $settings) ? $settings['commission'] : 0;

        $next = ($data[$last_index][0] === $trade['t']) ? 0 : 1;
        $data[$last_index+$next][0] = $trade['t'];
        $data[$last_index+$next][1] = $trade['o'];
        $data[$last_index+$next][2] = $trade['h'];
        $data[$last_index+$next][3] = $trade['l'];
        $data[$last_index+$next][4] = $trade['c'];
        $data[$last_index+$next][5] = $trade['q'];
        $data[$last_index+$next][6] = $trade['T'];

        if($next){
            array_splice($data, 0, 1);
        }

        $analysis = new Analysis();
        $closed = array_map(function($el){return floatval($el[4]);}, $data);
        $is_rsi = false;
        if(!empty($settings['rsi_period'])) {
            $rsi = $analysis->rsi($closed, $settings['rsi_period']);
            $is_rsi = true;
        }else{
            $rsi = [];
        }
        $is_stoch = false;
        if(!empty($settings['stoch_rsi_period'])){
            $stoch_rsi = $analysis->stoch_rsi($rsi,$settings['rsi_period'],$settings['stoch_rsi_period']);
            $is_stoch = true;
        }else{
            $stoch_rsi = [];
        }
//        2) Робити аналіз покупки чи продажу. Якщо є покупка чи продаж, але не створилася нова свічка, тоді треба зберегти $mark в $market. Якщо створилася нова свічка то скинути $market['mark'] на false, і записати в базу теперішній $mark.
        if(array_key_exists('status',$market)){
            $status = $market['status'];
            $balance = array_key_exists('balance',$market) ? $market['balance'] : floatval($settings['start_balance']);
            $old_balance = array_key_exists('old_balance',$market) ? $market['old_balance'] : floatval($settings['start_balance']);
        }else{
            $simulation = \App\Models\Simulation::where('market_id',$market['id'])->latest('id')->first();
            if($simulation){
                $status = $simulation->action === 'buy' ? 'bought' : 'deposit';
                $balance = $simulation->result;
                $old_balance = $simulation->value;
            }else{
                $status = 'deposit';
                $balance = floatval($settings['start_balance']);
                $old_balance = floatval($settings['start_balance']);
            }
        }

        $i = $last_index;
        if($is_stoch){
            $stoch_rsi_logic = '';
            $sr = $stoch_rsi['stoch_rsi'];
            $ssr = $stoch_rsi['sma_stoch_rsi'];
            if(array_key_exists($i-1,$ssr)){
                if($sr[$i] > 80 && $ssr[$i] > 80){
                    if($sr[$i-1] >= $sr[$i] && $ssr[$i-1] >= $ssr[$i] && $sr[$i-1] >= $ssr[$i-1] && $sr[$i] <= $ssr[$i]){
                        $stoch_rsi_logic = 'down';
                    }
                }elseif($sr[$i] < 20 && $ssr[$i] < 20){
                    if($sr[$i-1] <= $sr[$i] && $ssr[$i-1] <= $ssr[$i] && $sr[$i-1] <= $ssr[$i-1] && $sr[$i] >= $ssr[$i]){
                        $stoch_rsi_logic = 'up';
                    }
                }
            }
        }
        $close = $trade['c'];

        if($status == 'deposit' && ($is_rsi ? $rsi[$i] <= $settings['rsi_min'] : true) &&
            ($is_stoch ? $stoch_rsi_logic === 'up' : true)){
            $status = 'bought';
            $old_balance = $balance;
            // TODO: зробити скорочення до 0.000000
            if($market['is_trade']){
                try{
                    $res = $market['api']->marketQuoteBuy($market['name'],$balance);
                    $console->info('market '.$market['id'].' buy: ' . json_encode($res));
                    $balance = 0;
                    $commission_sum = 0;
                    foreach ($res['fills'] as $fill){
                        $balance += floatval($fill['qty']);
                        $commission_sum += floatval($fill['commission']);
                    }
                    $close = floor($old_balance / $balance * 100) / 100;
                    $balance = $balance - $commission_sum;
                }catch (\Exception $e){
                    $balance = $close ? $balance / $close : $balance;
                    $balance = floor($balance * (1 - $commission) * 1000000000) / 1000000000;
                }
            }else{
                $balance = $close ? $balance / $close : $balance;
                $balance = floor($balance * (1 - $commission) * 1000000000) / 1000000000;
            }
            Simulation::create([
                'market_id' => $market['id'],
                'action' => 'buy',
                'value' => $old_balance,
                'result' => $balance,
                'price' => $close,
                'rsi' => $is_rsi ? $rsi[$i] : 0,
                'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                'time' => date("Y-m-d H:i:s",intval($trade['T'])/1000)
            ]);
            $market['mark'] = 'buy';
        }elseif($status == 'bought'){
            $is_profit = floatval($settings['profit_limit']) == 0.0 ? false : $balance * $close > $old_balance * (1 + floatval($settings['profit_limit']));
            if((intval($settings['rsi_max']) > 0 && ($is_rsi ? $rsi[$i] >= $settings['rsi_max'] : true) &&
                    ($is_stoch ? $stoch_rsi_logic === 'down' : true)) || $is_profit){
                $status = 'deposit';
                $old_balance = $balance;
                // TODO: зробити скорочення до 0.00
                if($market['is_trade']){
                    try{
                        $res = $market['api']->marketSell($market['name'],$balance);
                        $console->info('market '.$market['id'].' sell: ' . json_encode($res));
                        $balance = 0;
                        $commission_sum = 0;
                        foreach ($res['fills'] as $fill){
                            $balance += floatval($fill['qty']) * floatval($fill['price']);
                            $commission_sum += floatval($fill['commission']);
                        }
                        $close = floor($balance / $old_balance * 100) / 100;
                        $balance = $balance - $commission_sum;
                    }catch (\Exception $e){
                        $balance = $balance * $close;
                        $balance = floor($balance * (1 - $commission) * 100) / 100;
                    }
                }else {
                    $balance = $balance * $close;
                    $balance = floor($balance * (1 - $commission) * 100) / 100;
                }
                Simulation::create([
                    'market_id' => $market['id'],
                    'action' => 'sell',
                    'value' => $old_balance,
                    'result' => $balance,
                    'price' => $close,
                    'rsi' => $is_rsi ? $rsi[$i] : 0,
                    'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                    'time' => date("Y-m-d H:i:s",intval($trade['T'])/1000)
                ]);
                $market['mark'] = 'sell';
            }
        }
        $market['status'] = $status;
        $market['balance'] = $balance;
        $market['old_balance'] = $old_balance;
//        ---------------------------------------

        if($next){
            $mark = $market['mark'];
            $market['mark'] = false;
            $market_db = Market::find($market['id']);
            $market_data = $market_db->data ?? [];
            array_push($market_data,['c' => $data[$last_closed_index], 'm' => $mark]);
            $market_rsi = $market_db->rsi ?? [];
            if(!empty($rsi)) array_push($market_rsi,$rsi[$last_closed_index]);
            $market_stoch_rsi = $market_db->stoch_rsi ?? ['stoch_rsi' => [], 'sma_stoch_rsi' => []];
            if(!empty($stoch_rsi)) array_push($market_stoch_rsi['stoch_rsi'],$stoch_rsi['stoch_rsi'][$last_closed_index]);
            if(!empty($stoch_rsi)) array_push($market_stoch_rsi['sma_stoch_rsi'],$stoch_rsi['sma_stoch_rsi'][$last_closed_index]);
            $market_db->update([
                'data' => $market_data,
                'rsi' => $market_rsi,
                'stoch_rsi' =>$market_stoch_rsi
            ]);
        }
        $market['data'] = $data;
        return $market;
    }

    public static function addNewCandleTest($market,$trade,$console)
    {
        $settings = $market['settings'];
        if($settings['candle'] !== $trade['i']) return $market;
        $data = $market['data'];
        $last_index = count($data)-1;
        $last_closed_index = count($data)-2;
        $commission = array_key_exists('commission', $settings) ? $settings['commission'] : 0;

        $next = ($data[$last_index][0] === $trade['t']) ? 0 : 1;
        $data[$last_index+$next][0] = $trade['t'];
        $data[$last_index+$next][1] = $trade['o'];
        $data[$last_index+$next][2] = $trade['h'];
        $data[$last_index+$next][3] = $trade['l'];
        $data[$last_index+$next][4] = $trade['c'];
        $data[$last_index+$next][5] = $trade['q'];
        $data[$last_index+$next][6] = $trade['T'];

        if($next){
            array_splice($data, 0, 1);
        }

        $analysis = new Analysis();
        $closed = array_map(function($el){return floatval($el[4]);}, $data);
        $is_rsi = false;
        if(!empty($settings['rsi_period'])) {
            $rsi = $analysis->rsi($closed, $settings['rsi_period']);
            $is_rsi = true;
        }else{
            $rsi = [];
        }
        $is_stoch = false;
        if(!empty($settings['stoch_rsi_period'])){
            $stoch_rsi = $analysis->stoch_rsi($rsi,$settings['rsi_period'],$settings['stoch_rsi_period']);
            $is_stoch = true;
        }else{
            $stoch_rsi = [];
        }
//        2) Робити аналіз покупки чи продажу. Якщо є покупка чи продаж, але не створилася нова свічка, тоді треба зберегти $mark в $market. Якщо створилася нова свічка то скинути $market['mark'] на false, і записати в базу теперішній $mark.
        if(array_key_exists('status',$market)){
            $status = $market['status'];
            $balance = array_key_exists('balance',$market) ? $market['balance'] : floatval($settings['start_balance']);
            $old_balance = array_key_exists('old_balance',$market) ? $market['old_balance'] : floatval($settings['start_balance']);
        }else{
            $simulation = \App\Models\Simulation::where('market_id',$market['id'])->latest('id')->first();
            if($simulation){
                $status = $simulation->action === 'buy' ? 'bought' : 'deposit';
                $balance = $simulation->result;
                $old_balance = $simulation->value;
            }else{
                $status = 'deposit';
                $balance = floatval($settings['start_balance']);
                $old_balance = floatval($settings['start_balance']);
            }
        }

        $i = $last_index;
        if($is_stoch){
            $stoch_rsi_logic = '';
            $sr = $stoch_rsi['stoch_rsi'];
            $ssr = $stoch_rsi['sma_stoch_rsi'];
            if(array_key_exists($i-1,$ssr)){
                if($sr[$i] > 80 && $ssr[$i] > 80){
                    if($sr[$i-1] >= $sr[$i] && $ssr[$i-1] >= $ssr[$i] && $sr[$i-1] >= $ssr[$i-1] && $sr[$i] <= $ssr[$i]){
                        $stoch_rsi_logic = 'down';
                    }
                }elseif($sr[$i] < 20 && $ssr[$i] < 20){
                    if($sr[$i-1] <= $sr[$i] && $ssr[$i-1] <= $ssr[$i] && $sr[$i-1] <= $ssr[$i-1] && $sr[$i] >= $ssr[$i]){
                        $stoch_rsi_logic = 'up';
                    }
                }
            }
        }
        $close = $trade['c'];

        if($status == 'deposit' && ($is_rsi ? $rsi[$i] <= $settings['rsi_min'] : true) &&
            ($is_stoch ? $stoch_rsi_logic === 'up' : true)){
            $status = 'bought';
            $old_balance = $balance;
            // TODO: зробити скорочення до 0.000000
            if($market['is_trade']){
                try{
//                    $res = $market['api']->marketQuoteBuy($market['name'],$balance);
//                    $console->info('market '.$market['id'].' buy: ' . json_encode($res));
//                    $balance = 0;
//                    $commission_sum = 0;
//                    foreach ($res['fills'] as $fill){
//                        $balance += floatval($fill['qty']);
//                        $commission_sum += floatval($fill['commission']);
//                    }
//                    $close = floor($old_balance / $balance * 100) / 100;
//                    $balance = $balance - $commission_sum;
                    $balance = $close ? $balance / $close : $balance;
                    $balance = floor($balance * (1 - $commission) * 1000000000) / 1000000000;
                    $console->info('market '.$market['id'].' buy: '.json_encode(['price' => $close,'old_balance' => $old_balance, 'balance' => $balance]));
                }catch (\Exception $e){
                    $balance = $close ? $balance / $close : $balance;
                    $balance = floor($balance * (1 - $commission) * 1000000000) / 1000000000;
                }
            }else{
                $balance = $close ? $balance / $close : $balance;
                $balance = floor($balance * (1 - $commission) * 1000000000) / 1000000000;
            }
            Simulation::create([
                'market_id' => $market['id'],
                'action' => 'buy',
                'value' => $old_balance,
                'result' => $balance,
                'price' => $close,
                'rsi' => $is_rsi ? $rsi[$i] : 0,
                'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                'time' => date("Y-m-d H:i:s",intval($trade['T'])/1000)
            ]);
            $market['mark'] = 'buy';
        }elseif($status == 'bought'){
            $is_profit = floatval($settings['profit_limit']) == 0.0 ? false : $balance * $close > $old_balance * (1 + floatval($settings['profit_limit']));
            if((intval($settings['rsi_max']) > 0 && ($is_rsi ? $rsi[$i] >= $settings['rsi_max'] : true) &&
                    ($is_stoch ? $stoch_rsi_logic === 'down' : true)) || $is_profit){
                $status = 'deposit';
                $old_balance = $balance;
                // TODO: зробити скорочення до 0.00
                if($market['is_trade']){
                    try{
//                        $res = $market['api']->marketSell($market['name'],$balance);
//                        $console->info('market '.$market['id'].' sell: ' . json_encode($res));
//                        $balance = 0;
//                        $commission_sum = 0;
//                        foreach ($res['fills'] as $fill){
//                            $balance += floatval($fill['qty']) * floatval($fill['price']);
//                            $commission_sum += floatval($fill['commission']);
//                        }
//                        $close = floor($balance / $old_balance * 100) / 100;
//                        $balance = $balance - $commission_sum;
                        $balance = $balance * $close;
                        $balance = floor($balance * (1 - $commission) * 100) / 100;
                        $console->info('market '.$market['id'].' sell: '.json_encode(['price' => $close,'old_balance' => $old_balance, 'balance' => $balance]));
                    }catch (\Exception $e){
                        $balance = $balance * $close;
                        $balance = floor($balance * (1 - $commission) * 100) / 100;
                    }
                }else {
                    $balance = $balance * $close;
                    $balance = floor($balance * (1 - $commission) * 100) / 100;
                }
                Simulation::create([
                    'market_id' => $market['id'],
                    'action' => 'sell',
                    'value' => $old_balance,
                    'result' => $balance,
                    'price' => $close,
                    'rsi' => $is_rsi ? $rsi[$i] : 0,
                    'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                    'time' => date("Y-m-d H:i:s",intval($trade['T'])/1000)
                ]);
                $market['mark'] = 'sell';
            }
        }
        $market['status'] = $status;
        $market['balance'] = $balance;
        $market['old_balance'] = $old_balance;
//        ---------------------------------------

        if($next){
            $mark = $market['mark'];
            $market['mark'] = false;
            $market_db = Market::find($market['id']);
            $market_data = $market_db->data ?? [];
            array_push($market_data,['c' => $data[$last_closed_index], 'm' => $mark]);
            $market_rsi = $market_db->rsi ?? [];
            if(!empty($rsi)) array_push($market_rsi,$rsi[$last_closed_index]);
            $market_stoch_rsi = $market_db->stoch_rsi ?? ['stoch_rsi' => [], 'sma_stoch_rsi' => []];
            if(!empty($stoch_rsi)) array_push($market_stoch_rsi['stoch_rsi'],$stoch_rsi['stoch_rsi'][$last_closed_index]);
            if(!empty($stoch_rsi)) array_push($market_stoch_rsi['sma_stoch_rsi'],$stoch_rsi['sma_stoch_rsi'][$last_closed_index]);
            $market_db->update([
                'data' => $market_data,
                'rsi' => $market_rsi,
                'stoch_rsi' =>$market_stoch_rsi
            ]);
        }
        $market['data'] = $data;
        return $market;
    }
}
