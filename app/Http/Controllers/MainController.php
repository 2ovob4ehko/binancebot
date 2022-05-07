<?php

namespace App\Http\Controllers;

use App\Helpers\Analysis;
use App\Models\Market;
use App\Models\Simulation;
use Auth;
use DB;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function showMarket($id,Request $request)
    {
        if($id){
            $market = Market::findOrFail($id);
        }else{
            $market = new Market();
        }
        $candle_intervals = ['1m','3m','5m','15m','30m','1h','2h','4h','6h','8h','12h','1d','3d','1w','1M'];
        return view('market',[
            'market' => $market,
            'candle_intervals' => $candle_intervals
        ]);
    }

    public function saveMarket(Request $request)
    {
        if($request->has('id')){
            Market::where('id',$request->id)->update([
                'name' => $request->name,
                'settings' => [
                    'candle' => $request->candle,
                    'period' => $request->period,
                    'rsi_period' => $request->rsi_period,
                    'stoch_rsi_period' => $request->stoch_rsi_period,
                    'rsi_min' => $request->rsi_min,
                    'rsi_max' => $request->rsi_max,
                    'profit_limit' => $request->profit_limit,
                    'start_balance' => $request->start_balance,
                ]
            ]);
            $id = $request->id;
        }else{
            $market = Market::create([
                'name' => $request->name,
                'settings' => [
                    'candle' => $request->candle,
                    'period' => $request->period,
                    'rsi_period' => $request->rsi_period,
                    'stoch_rsi_period' => $request->stoch_rsi_period,
                    'rsi_min' => $request->rsi_min,
                    'rsi_max' => $request->rsi_max,
                    'profit_limit' => $request->profit_limit,
                    'start_balance' => $request->start_balance,
                ],
                'data' => '',
                'rsi' => '',
                'result' => '',
            ]);
            $id = $market->id;
        }
        return redirect('/market/'.$id)->with('message', 'Налаштування збережено');
    }

    public function deleteMarket($id, Request $request)
    {
        Market::where('id',$id)->delete();
        return ["success" => true, "message" => 'Маркет видалено'];
    }

    public function analysisMarket($id, Request $request)
    {
        $market = Market::where('id',$id)->first();
        $settings = $market->settings;
        $analysis = new Analysis();

        $candles = $this->multilimitQuery($market->name,$settings['candle'],$market->limit());
//        $candles = $analysis->fakeData();
        $closed = array_map(function($el){return floatval($el[4]);}, $candles);

        $rsi = $analysis->rsi($closed,$settings['rsi_period']);

        if(!empty($settings['stoch_rsi_period'])){
            $stoch_rsi = $analysis->stoch_rsi($rsi,$settings['rsi_period'],$settings['stoch_rsi_period']);
        }else{
            $stoch_rsi = [];
        }

        $result = $this->makeSimulation($market,$rsi,$stoch_rsi,$candles);

        $market->data = $result['data'];
        $market->rsi = $rsi;
        $market->stoch_rsi = $stoch_rsi;
        $market->result = $result['finish'];
        $market->save();
        return ["success" => true, "message" => 'Аналіз проведено','logs' => $result['logs']];
    }

    private function makeSimulation($market,$rsi,$stoch_rsi,$candles)
    {
        $logs = [];
//        remove old data of the simulation
        Simulation::where('market_id',$market->id)->delete();
        $settings = $market->settings;
        $data = [];
//        get name of currencies
        if(strlen($market->name) > 6){
            $subC = 'USDT';
            $mainC = str_replace('USDT','',$market->name);
        }else{
            $parts = str_split($market->name,3);
            $subC = $parts[1];
            $mainC = $parts[0];
        }
        $balance = floatval($settings['start_balance']);
        $old_balance = floatval($settings['start_balance']);
        $status = 'deposit';
        for ($i=0;$i<count($candles);$i++){
//          next candle if indicator has no data at this time
            $is_stoch = false;
            if(!empty($settings['stoch_rsi_period'])){
                if(!array_key_exists($i,$stoch_rsi['sma_stoch_rsi'])){
                    array_push($data,['c' => $candles[$i], 'm' => false]);
                    continue;
                }
                $is_stoch = true;
            }else{
                if(!array_key_exists($i,$rsi)){
                    array_push($data,['c' => $candles[$i], 'm' => false]);
                    continue;
                }
            }
//            getting stoch indicator logic
            if($is_stoch){
                $stoch_rsi_logic = '';
                $sr = $stoch_rsi['stoch_rsi'];
                $ssr = $stoch_rsi['sma_stoch_rsi'];
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
            $mark = false;
            $close = floatval($candles[$i][4]);

            $logs[] = '['.date("Y-m-d H:i:s",$candles[$i][6]/1000).'] status: '.$status.' rsi: '.$rsi[$i].
            ' stoch up '.(int)($is_stoch ? $stoch_rsi_logic === 'up' : true).
                ' stoch down '.(int)($is_stoch ? $stoch_rsi_logic === 'down' : true);

            if($status == 'deposit' && $rsi[$i] <= $settings['rsi_min'] &&
                ($is_stoch ? $stoch_rsi_logic === 'up' : true)){
                $status = 'bought';
                $old_balance = $balance;
                // TODO: зробити скорочення до 0.000000
                $balance = $close ? floor($balance / $close * 1000000000) / 1000000000 : $balance;
                Simulation::create([
                    'market_id' => $market->id,
                    'action' => 'buy',
                    'value' => $old_balance,
                    'result' => $balance,
                    'price' => $close,
                    'rsi' => $rsi[$i],
                    'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                    'time' => date("Y-m-d H:i:s",$candles[$i][6]/1000)
                ]);
                $mark = 'buy';
            }elseif($status == 'bought'){
                $is_profit = floatval($settings['profit_limit']) == 0.0 ? false : $balance * $close > $old_balance * (1 + floatval($settings['profit_limit']));
                if((intval($settings['rsi_max']) > 0 && $rsi[$i] >= $settings['rsi_max'] &&
                        ($is_stoch ? $stoch_rsi_logic === 'down' : true)) || $is_profit){
                    $status = 'deposit';
                    $old_balance = $balance;
                    // TODO: зробити скорочення до 0.00
                    $balance = floor($balance * $close * 100) / 100;
                    Simulation::create([
                        'market_id' => $market->id,
                        'action' => 'sell',
                        'value' => $old_balance,
                        'result' => $balance,
                        'price' => $close,
                        'rsi' => $rsi[$i],
                        'stoch_rsi' => $is_stoch ? $stoch_rsi['stoch_rsi'][$i] : 0,
                        'time' => date("Y-m-d H:i:s",$candles[$i][6]/1000)
                    ]);
                    $mark = 'sell';
                }
            }
            array_push($data,['c' => $candles[$i], 'm' => $mark]);
        }
        $currency = $status == 'bought' ? $mainC : $subC;
        return [
            'finish' => $balance.' '.$currency,
            'data' => $data,
            'logs' => $logs
        ];
    }

    public static function multilimitQueryS($symbol,$interval,$limit){
        $self = new MainController();
        return $self->multilimitQuery($symbol,$interval,$limit);
    }
    private function multilimitQuery($symbol,$interval,$limit)
    {
        $times = intval($limit / 1000);
        $rest = $limit % 1000;
        $candles = [];
        $time = time() * 1000;
        for($i=0;$i<$times;$i++){
            $json = file_get_contents('https://api.binance.com/api/v3/klines?symbol='.$symbol.'&interval='.$interval.'&limit=1000&endTime='.$time);
            $data = json_decode($json);
            $time = $data[0][0];
            $candles = array_merge($data,$candles);
        }
        if($rest>0){
            $json = file_get_contents('https://api.binance.com/api/v3/klines?symbol='.$symbol.'&interval='.$interval.'&limit='.$rest.'&endTime='.$time);
            $data = json_decode($json);
            $candles = array_merge($data,$candles);
        }
        $candles = array_map(function($item){
            return array_slice($item,0,7);
        },$candles);
        return $candles;
    }

}
