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
            $commission = 0.001;
        }
        $settings = [
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
            'buy_again_lower' => $request->buy_again_lower ?? null,
            'buy_again_amount' => $request->buy_again_amount ?? null,
            'buy_again_count_limit' => $request->buy_again_count_limit ?? null,
            'buy_again_lower_progress' => $request->buy_again_lower_progress ?? null,
            'buy_again_amount_progress' => $request->buy_again_amount_progress ?? null,
            'stop_on_down' => $request->has('stop_on_down'),
        ];
        if($request->has('id')){
            if(!Auth::user()->markets->contains($request->id)) return redirect('/');
            $market = Market::find($request->id);
            $market->update([
                'name' => $name,
                'settings' => $settings,
                'is_online' => $request->has('is_online'),
                'is_trade' => $request->has('is_trade')
            ]);
            $id = $request->id;
        }else{
            $market = Market::create([
                'user_id' => Auth::user()->id,
                'name' => $name,
                'settings' => $settings,
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

    public function clear_charts($id, Request $request)
    {
        if(!Auth::user()->markets->contains($id)) return redirect('/');
        $market = Market::find($id);
        Simulation::where('market_id',$id)->delete();
        Chart::where('market_id',$id)->delete();
        $market->update([
            'data' => [],
            'rsi' => [],
            'result' => '',
            'stoch_rsi' => ['stoch_rsi' => [], 'sma_stoch_rsi' => []],
            'trade_data' => null,
        ]);
        return ["success" => true, "message" => 'Маркет очищено'];
    }

    public function delete($id, Request $request)
    {
        if(!Auth::user()->markets->contains($id)) return redirect('/');
        Simulation::where('market_id',$id)->delete();
        Market::where('id',$id)->delete();
        return ["success" => true, "message" => 'Маркет видалено'];
    }

    public function analysis($id, Request $request)
    {
        set_time_limit(100000);
        ini_set('memory_limit','2048M');
        $market = Market::where('id',$id)->first();
        if($market->is_online || $market->is_trade) return ["success" => false, "message" => 'Онлайн маркет не може проходити аналіз'];;
        $settings = $market->settings;
        Simulation::where('market_id',$market->id)->delete();
        Chart::where('market_id',$market->id)->delete();
        Market::where('id',$market->id)->update([
            'data' => [],
            'rsi' => [],
            'result' => '',
            'stoch_rsi' => ['stoch_rsi' => [], 'sma_stoch_rsi' => []],
            'trade_data' => null,
        ]);

        $candles = $this->multilimitCSVQuery($market->name,$settings['candle'],$settings['period']);
        $market_data = [
            'id' => $market->id,
            'name' => $market->name,
            'settings' => $market->settings,
            'data' => array_slice($candles, -50),
            'mark' => false,
            'is_trade' => false,
            'current_buy_again_lower' => floatval($market->settings['buy_again_lower']) ?? 0,
            'current_buy_again_balance' => floatval($market->settings['buy_again_amount']),
            'buy_again_history' => [],
            'result' => 0,
            'api' => false
        ];

        for ($i=0;$i<count($candles);$i++){
            $candle_data = [
                't' => $candles[$i][0],
                'o' => $candles[$i][1],
                'h' => $candles[$i][2],
                'l' => $candles[$i][3],
                'c' => $candles[$i][4],
                'q' => $candles[$i][5],
                'T' => $candles[$i][6],
                'i' => $settings['candle']
            ];
            $trading = new Trading($market_data, $candle_data, false);
            $market_data = $trading->addNewCandle();
        }

        return ["success" => true, "message" => 'Аналіз проведено'];
    }

    public static function multilimitQueryS($symbol,$interval,$limit){
        $self = new MarketController();
        return $self->multilimitCSVQuery($symbol,$interval,$limit);
    }
    public static function multilimitQuery($symbol,$interval,$limit,$time = null)
    {
        $times = intval($limit / 1000);
        $rest = $limit % 1000;
        $candles = [];
        $time = $time ?? time() * 1000;
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
