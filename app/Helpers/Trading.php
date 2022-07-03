<?php

namespace App\Helpers;

use App\Http\Controllers\MarketController;
use App\Models\Market;
use App\Models\Simulation;
use Binance\API;

class Trading
{
    public $market;
    public $trade;
    public $console;
    public $settings;
    public $last_index;
    public $last_closed_index;
    public $next;
    public $data;
    public $is_rsi;
    public $rsi;
    public $is_stoch;
    public $stoch_rsi;
    public $status;
    public $balance;
    public $stoch_rsi_logic;

    /**
     * Trading constructor.
     * @param $market
     * @param $trade
     * @param $console
     */
    public function __construct($market,$trade,$console)
    {
        $this->market = $market;
        $this->trade = $trade;
        $this->console = $console;
        $this->settings = $market['settings'];
        $this->data = $market['data'];
        $this->last_index = count($this->data)-1;
        $this->last_closed_index = count($this->data)-2;
        $this->next = ($this->data[$this->last_index][0] === $trade['t']) ? 0 : 1;
    }

    /**
     * @return array of markets
     */
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
                $api = new API($api_key, $secret_key);
                $api->caOverride = true;
                $output[$market->id] = [
                    'id' => $market->id,
                    'name' => $market->name,
                    'settings' => $market->settings,
                    'data' => $candles,
                    'mark' => false,
                    'is_trade' => $market->is_trade,
                    'api' => $api
                ];
            }
        }
        return $output;
    }

    /**
     * Adding candle data to the last candle of the market
     */
    function addCandleData()
    {
        $index = $this->last_index + $this->next;
        $this->data[$index][0] = $this->trade['t'];
        $this->data[$index][1] = $this->trade['o'];
        $this->data[$index][2] = $this->trade['h'];
        $this->data[$index][3] = $this->trade['l'];
        $this->data[$index][4] = $this->trade['c'];
        $this->data[$index][5] = $this->trade['q'];
        $this->data[$index][6] = $this->trade['T'];

        if($this->next){
            array_splice($this->data, 0, 1);
        }
        $this->market['data'] = $this->data;
    }

    /**
     * Getting params of analisis
     */
    function makeAnalis()
    {
        $analysis = new Analysis();
        $closed = array_map(function($el){return floatval($el[4]);}, $this->data);
        $this->is_rsi = false;
        if(!empty($this->settings['rsi_period'])) {
            $this->rsi = $analysis->rsi($closed, $this->settings['rsi_period']);
            $this->is_rsi = true;
        }else{
            $this->rsi = [];
        }
        $this->is_stoch = false;
        if(!empty($this->settings['stoch_rsi_period'])){
            $this->stoch_rsi = $analysis->stoch_rsi($this->rsi,$this->settings['rsi_period'],$this->settings['stoch_rsi_period']);
            $this->is_stoch = true;
        }else{
            $this->stoch_rsi = [];
        }
    }

    /**
     * Getting balance from market data or from simulation history
     */
    function getLastBalance()
    {
        if(array_key_exists('status',$this->market)){
            $this->status = $this->market['status'];
            $this->balance = array_key_exists('balance',$this->market) ? $this->market['balance'] : floatval($this->settings['start_balance']);
        }else{
            $simulation = Simulation::where('market_id',$this->market['id'])->latest('id')->first();
            if($simulation){
                $this->status = $simulation->action === 'buy' ? 'bought' : 'deposit';
                $this->balance = $simulation->result;
            }else{
                $this->status = 'deposit';
                $this->balance = floatval($this->settings['start_balance']);
            }
        }
    }

    /**
     * Getting Stoch RSI logic up or down moving
     */
    function getStochRsiLogic()
    {
        $i = $this->last_index;
        if($this->is_stoch){
            $this->stoch_rsi_logic = '';
            $sr = $this->stoch_rsi['stoch_rsi'];
            $ssr = $this->stoch_rsi['sma_stoch_rsi'];
            if(array_key_exists($i-1,$ssr)){
                if($sr[$i] > 80 && $ssr[$i] > 80){
                    if($sr[$i-1] >= $sr[$i] && $ssr[$i-1] >= $ssr[$i] && $sr[$i-1] >= $ssr[$i-1] && $sr[$i] <= $ssr[$i]){
                        $this->stoch_rsi_logic = 'down';
                    }
                }elseif($sr[$i] < 20 && $ssr[$i] < 20){
                    if($sr[$i-1] <= $sr[$i] && $ssr[$i-1] <= $ssr[$i] && $sr[$i-1] <= $ssr[$i-1] && $sr[$i] >= $ssr[$i]){
                        $this->stoch_rsi_logic = 'up';
                    }
                }
            }
        }
    }

    function makeTrade()
    {
        $commission = array_key_exists('commission', $this->settings) ? $this->settings['commission'] : 0;
        $close = $this->trade['c'];
        $trade_OK = true;
        $old_balance = $this->balance;

        $rsi_buy_rule = !$this->is_rsi || $this->rsi[$this->last_index] <= $this->settings['rsi_min'];
        $rsi_sell_rule = intval($this->settings['rsi_max']) > 0 &&
            (!$this->is_rsi || $this->rsi[$this->last_index] >= $this->settings['rsi_max']);

        $stoch_buy_rule = !$this->is_stoch || $this->stoch_rsi_logic === 'up';
        $stoch_sell_rule = !$this->is_stoch || $this->stoch_rsi_logic === 'down';

        $is_profit = !(floatval($this->settings['profit_limit']) == 0.0) &&
            $this->balance * $close > $this->balance * (1 + floatval($this->settings['profit_limit']));

        if($this->status == 'deposit' && $rsi_buy_rule && $stoch_buy_rule){
            $this->status = 'bought';
            $this->balance = floatval($this->settings['start_balance']); // stable set amount of quote for buying
            // TODO: зробити скорочення до 0.000000
            if($this->market['is_trade']){
                try{
                    $res = $this->market['api']->marketQuoteBuy($this->market['name'],$this->balance);
                    $this->console->info('market '.$this->market['id'].' buy: ' . json_encode($res));
                    $this->balance = 0;
                    $commission_sum = 0;
                    foreach ($res['fills'] as $fill){
                        $this->balance += floatval($fill['qty']);
                        if(str_contains($this->market['name'], $fill['commissionAsset'])){
                            $commission_sum += floatval($fill['commission']);
                        }
                    }
                    $old_balance = floatval($res['cummulativeQuoteQty']);
                    $close = $old_balance / $this->balance;
                    $this->balance = $this->balance - $commission_sum;
                }catch (\Exception $e){
                    $trade_OK = false;
                    $this->console->info('market '.$this->market['id'].' buy error: ' . $e->getMessage());
                    $this->telegram_log('market '.$this->market['name'].' '.$this->market['id'].' buy error: ' . $e->getMessage());
                }
            }else{
                $this->balance = $close ? $this->balance / $close : $this->balance;
                $this->balance = floor($this->balance * (1 - $commission) * 10**$this->settings['baseAssetPrecision']) / 10**$this->settings['baseAssetPrecision'];
            }
            if($trade_OK){
                Simulation::create([
                    'market_id' => $this->market['id'],
                    'action' => 'buy',
                    'value' => $old_balance,
                    'result' => $this->balance,
                    'price' => $close,
                    'rsi' => $this->is_rsi ? $this->rsi[$this->last_index] : 0,
                    'stoch_rsi' => $this->is_stoch ? $this->stoch_rsi['stoch_rsi'][$this->last_index] : 0,
                    'time' => date("Y-m-d H:i:s",intval($this->trade['T'])/1000)
                ]);
                $this->market['mark'] = 'buy';
            }
        }elseif($this->status == 'bought' && (($rsi_sell_rule && $stoch_sell_rule) || $is_profit)){
            $status = 'deposit';
            // TODO: зробити скорочення до 0.00
            if($this->market['is_trade']){
                try{
                    $res = $this->market['api']->marketSell($this->market['name'],$this->balance);
                    $this->console->info('market '.$this->market['id'].' sell: ' . json_encode($res));
                    $this->balance = 0;
                    $commission_sum = 0;
                    foreach ($res['fills'] as $fill){
                        $this->balance += floatval($fill['qty']) * floatval($fill['price']);
                        if(str_contains($this->market['name'], $fill['commissionAsset'])){
                            $commission_sum += floatval($fill['commission']);
                        }
                    }
                    $old_balance = floatval($res['executedQty']);
                    $close = $this->balance / $old_balance;
                    $this->balance = $this->balance - $commission_sum;
                }catch (\Exception $e){
                    $trade_OK = false;
                    $this->console->info('market '.$this->market['id'].' sell error: ' . $e->getMessage());
                    $this->telegram_log('market '.$this->market['name'].' '.$this->market['id'].' sell error: ' . $e->getMessage());
                }
            }else {
                $this->balance = $this->balance * $close;
                $this->balance = floor($this->balance * (1 - $commission) * 10**$this->settings['quoteAssetPrecision']) / 10**$this->settings['quoteAssetPrecision'];
            }
            if($trade_OK){
                Simulation::create([
                    'market_id' => $this->market['id'],
                    'action' => 'sell',
                    'value' => $old_balance,
                    'result' => $this->balance,
                    'price' => $close,
                    'rsi' => $this->is_rsi ? $this->rsi[$this->last_index] : 0,
                    'stoch_rsi' => $this->is_stoch ? $this->stoch_rsi['stoch_rsi'][$this->last_index] : 0,
                    'time' => date("Y-m-d H:i:s",intval($this->trade['T'])/1000)
                ]);
                $this->market['mark'] = 'sell';
            }
        }
        if($trade_OK){
            $this->market['status'] = $this->status;
            $this->market['balance'] = $this->balance;
        }
    }

    /**
     * @return mixed
     */
    public function addNewCandle()
    {
        if($this->settings['candle'] !== $this->trade['i']) return $this->market;
        $this->addCandleData();
        $this->makeAnalis();
        $this->getLastBalance();
        $this->getStochRsiLogic();
        $this->checkPrecision();

//        2) Робити аналіз покупки чи продажу. Якщо є покупка чи продаж, але не створилася нова свічка, тоді треба зберегти $mark в $market. Якщо створилася нова свічка то скинути $market['mark'] на false, і записати в базу теперішній $mark.
        $this->makeTrade();

        if($this->next){
            $mark = $this->market['mark'];
            $this->market['mark'] = false;

            $market_db = Market::find($this->market['id']);
            $market_data = $market_db->data ?? [];
            array_push($market_data,['c' => $this->data[$this->last_closed_index], 'm' => $mark]);

            $market_rsi = $market_db->rsi ?? [];
            if(!empty($this->rsi)) array_push($market_rsi,$this->rsi[$this->last_closed_index]);

            $market_stoch_rsi = $market_db->stoch_rsi ?? ['stoch_rsi' => [], 'sma_stoch_rsi' => []];
            if(!empty($this->stoch_rsi)) array_push($market_stoch_rsi['stoch_rsi'],$this->stoch_rsi['stoch_rsi'][$this->last_closed_index]);
            if(!empty($this->stoch_rsi)) array_push($market_stoch_rsi['sma_stoch_rsi'],$this->stoch_rsi['sma_stoch_rsi'][$this->last_closed_index]);

            $market_db->update([
                'data' => $market_data,
                'rsi' => $market_rsi,
                'stoch_rsi' =>$market_stoch_rsi
            ]);
        }
        return $this->market;
    }

    function checkPrecision()
    {
        if(!array_key_exists('baseAsset',$this->settings) || !$this->settings['baseAsset']){
            $info = $this->market['api']->exchangeInfo()['symbols'][$this->market['name']];
            $this->settings['baseAsset'] = $info['baseAsset'];
            $this->settings['baseAssetPrecision'] = $info['baseAssetPrecision'];
            $this->settings['quoteAsset'] = $info['quoteAsset'];
            $this->settings['quoteAssetPrecision'] = $info['quoteAssetPrecision'];
            $this->market['settings'] = $this->settings;
            $market_db = Market::find($this->market['id']);
            $market_db->update([
                'settings' => $this->settings,
            ]);
        }
    }

    function telegram_log($text)
    {
        file_get_contents('https://api.telegram.org/bot5443827645:AAGY6C0f8YOLvqw9AtdxSoVcDVwuhQKO6PY/sendMessage?chat_id=600558355&text='.urlencode($text));
    }
}
