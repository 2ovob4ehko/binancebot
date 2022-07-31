<?php

namespace App\Http\Controllers;

use App\Helpers\Analysis;
use App\Helpers\Trading;
use App\Helpers\Intervals;
use App\Jobs\UploadCSVFromBinance;
use App\Models\Chart;
use App\Models\Market;
use App\Models\Simulation;
use Binance\API;
use Carbon\CarbonPeriod;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MarketController extends Controller
{
    public function show($id,Request $request)
    {
        if($id){
            if(!Auth::user()->markets->contains($id)) return redirect('/');
            $market = Market::findOrFail($id);
        }else{
            $market = new Market();
        }
        $candle_intervals = Intervals::titles();
        return view('market',[
            'market' => $market,
            'candle_intervals' => $candle_intervals
        ]);
    }

    public function save(Request $request)
    {
        $name = strtoupper(preg_replace('/[^\w]/', '', $request->name));
        $api = new API(
            Auth::user()->setting('api_key')->value ?? '',
            Auth::user()->setting('secret_key')->value ?? ''
        );
        $api->caOverride = true;
        try{
            $info = $api->exchangeInfo()['symbols'][$name];
            $baseAsset = $info['baseAsset'];
            $baseAssetPrecision = $info['baseAssetPrecision'];
            $quoteAsset = $info['quoteAsset'];
            $quoteAssetPrecision = $info['quoteAssetPrecision'];
            $commission = floatval($api->commissionFee($name)[0]['takerCommission']);
        }catch (Exception $e){
            $commission = 0;
        }
        if($request->has('id')){
            if(!Auth::user()->markets->contains($request->id)) return redirect('/');
            if($request->has('is_online') || $request->has('is_trade')){
                Simulation::where('market_id',$request->id)->delete();
                Chart::where('market_id',$request->id)->delete();
                Market::where('id',$request->id)->update([
                    'data' => [],
                    'rsi' => [],
                    'result' => '',
                    'stoch_rsi' => ['stoch_rsi' => [], 'sma_stoch_rsi' => []]
                ]);
            }
            Market::where('id',$request->id)->update([
                'name' => $name,
                'settings' => [
                    'candle' => $request->candle,
                    'period' => $request->period,
                    'rsi_period' => $request->rsi_period,
                    'stoch_rsi_period' => $request->stoch_rsi_period,
                    'rsi_min' => $request->rsi_min,
                    'rsi_max' => $request->rsi_max,
                    'profit_limit' => $request->profit_limit,
                    'start_balance' => $request->start_balance,
                    'commission' => $commission,
                    'baseAsset' => $baseAsset ?? null,
                    'baseAssetPrecision' => $baseAssetPrecision ?? null,
                    'quoteAsset' => $quoteAsset ?? null,
                    'quoteAssetPrecision' => $quoteAssetPrecision ?? null,
                ],
                'is_online' => $request->has('is_online'),
                'is_trade' => $request->has('is_trade')
            ]);
            $id = $request->id;
        }else{
            $market = Market::create([
                'user_id' => Auth::user()->id,
                'name' => $name,
                'settings' => [
                    'candle' => $request->candle,
                    'period' => $request->period,
                    'rsi_period' => $request->rsi_period,
                    'stoch_rsi_period' => $request->stoch_rsi_period,
                    'rsi_min' => $request->rsi_min,
                    'rsi_max' => $request->rsi_max,
                    'profit_limit' => $request->profit_limit,
                    'start_balance' => $request->start_balance,
                    'commission' => $commission,
                    'baseAsset' => $baseAsset ?? null,
                    'baseAssetPrecision' => $baseAssetPrecision ?? null,
                    'quoteAsset' => $quoteAsset ?? null,
                    'quoteAssetPrecision' => $quoteAssetPrecision ?? null,
                ],
                'data' => [],
                'rsi' => [],
                'stoch_rsi' => ['stoch_rsi' => [], 'sma_stoch_rsi' => []],
                'result' => '',
                'is_online' => $request->has('is_online'),
                'is_trade' => $request->has('is_trade'),
                'type' => 'spot'
            ]);
            $id = $market->id;
        }
        return redirect('/market/'.$id)->with('message', 'Налаштування збережено');
    }

    public function delete($id, Request $request)
    {
        if(!Auth::user()->markets->contains($id)) return redirect('/');
        Market::where('id',$id)->delete();
        return ["success" => true, "message" => 'Маркет видалено'];
    }

    public function analysis($id, Request $request)
    {
        set_time_limit(1000);
        $market = Market::where('id',$id)->first();
        if($market->is_online || $market->is_trade) return ["success" => false, "message" => 'Онлайн маркет не може проходити аналіз'];;
        $settings = $market->settings;
        $analysis = new Analysis();

        $candles = $this->multilimitCSVQuery($market->name,$settings['candle'],$settings['period']);
//        $candles = $analysis->fakeData();
        $closed = array_map(function($el){return floatval($el[4]);}, $candles);

        $rsi = $analysis->rsi($closed,$settings['rsi_period']);

        if(!empty($settings['stoch_rsi_period'])){
            $stoch_rsi = $analysis->stoch_rsi($rsi,$settings['rsi_period'],$settings['stoch_rsi_period']);
        }else{
            $stoch_rsi = [];
        }

        $result = $this->makeSimulation($market,$rsi,$stoch_rsi,$candles);

        Chart::where('market_id',$market->id)->delete();
        foreach ($result['data'] as $item) {
            $market->charts()->create([
                'type' => 'data',
                'time' => $item['c'][6],
                'data' => $item
            ]);
        }
        foreach ($rsi as $index => $item) {
            $market->charts()->create([
                'type' => 'rsi',
                'time' => $result['data'][$index]['c'][6],
                'data' => $item
            ]);
        }
        if(!empty($settings['stoch_rsi_period'])) {
            foreach ($stoch_rsi['stoch_rsi'] as $index => $item) {
                $market->charts()->create([
                    'type' => 'stoch_rsi',
                    'time' => $result['data'][$index]['c'][6],
                    'data' => $item
                ]);
            }
            foreach ($stoch_rsi['sma_stoch_rsi'] as $index => $item) {
                $market->charts()->create([
                    'type' => 'sma_stoch_rsi',
                    'time' => $result['data'][$index]['c'][6],
                    'data' => $item
                ]);
            }
        }

//        $market->data = $result['data'];
//        $market->rsi = $rsi;
//        $market->stoch_rsi = $stoch_rsi;
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

        $balance = floatval($settings['start_balance']);
        $old_balance = floatval($settings['start_balance']);
        $commission = array_key_exists('commission', $settings) ? $settings['commission'] : 0;
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
            $mark = false;
            $close = floatval($candles[$i][4]);

            $logs[] = '['.date("Y-m-d H:i:s",$candles[$i][6]/1000).'] status: '.$status.' rsi: '.$rsi[$i].
            ' stoch up '.(int)($is_stoch ? $stoch_rsi_logic === 'up' : true).
                ' stoch down '.(int)($is_stoch ? $stoch_rsi_logic === 'down' : true);

            if($status == 'deposit' && $rsi[$i] <= $settings['rsi_min'] &&
                ($is_stoch ? $stoch_rsi_logic === 'up' : true)){
                $status = 'bought';
                $old_balance = $balance;
                $balance = $close ? $balance / $close : $balance;
                $balance = floor($balance * (1 - $commission) * 10**$settings['baseAssetPrecision']) / 10**$settings['baseAssetPrecision'];
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
                    $balance = $balance * $close;
                    $balance = floor($balance * (1 - $commission) * 10**$settings['quoteAssetPrecision']) / 10**$settings['quoteAssetPrecision'];
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
        $currency = $status == 'bought' ? $settings['baseAsset'] : $settings['quoteAsset'];
        return [
            'finish' => $balance.' '.$currency,
            'data' => $data,
            'logs' => $logs
        ];
    }

    public static function multilimitQueryS($symbol,$interval,$limit){
        $self = new MarketController();
        return $self->multilimitCSVQuery($symbol,$interval,$limit);
    }
    public static function multilimitQuery($symbol,$interval,$limit)
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

    private function multilimitCSVQuery($symbol,$interval,$days)
    {
        dispatch(new UploadCSVFromBinance($symbol));
        $candles = [];
        $times = 0;
        $periods = array_reverse(CarbonPeriod::create('2020-01-01', 'yesterday')->toArray());
        foreach ($periods as $dt){
            $date = $dt->toDateString();
            $filename = $symbol.'/'.$interval.'/'.$symbol.'-'.$interval.'-'.$date.'.csv';
            if(!Storage::disk('local')->exists($filename)) continue;
            $times++;
            if($times > $days) break;
            $csv = Storage::get($filename);
            $lines = array_filter(explode("\n",$csv));
            $data = array_map(function($el){
                $res = explode(',',$el);
                $res[0] = intval($res[0]);
                $res[6] = intval($res[6]);
                return $res;
            },$lines);
            $candles = array_merge($data,$candles);
        }
        $candles = array_map(function($item){
            return array_slice($item,0,7);
        },$candles);
        return $candles;
    }

    public function uploadCSVFromBinance($market)
    {
        Market::where('name',$market)->update(['upload_status' => 'uploading']);
        dispatch(new UploadCSVFromBinance($market));
        return ["success" => true, "message" => 'База маркету почала завантажуватися'];
    }

    public function tradeList()
    {
        $markets = Auth::user()->markets()->where('type','spot')->where('is_trade',1)->get();
        return view('home',[
            'markets' => $markets
        ]);
    }
}
