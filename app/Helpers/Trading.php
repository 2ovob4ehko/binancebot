<?php

namespace App\Helpers;

use App\Http\Controllers\MarketController;
use App\Models\Market;
use App\Models\Order;
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
    public $old_balance;
    public $stoch_rsi_logic;
    public $old_price;
    public $test;

    /**
     * Trading constructor.
     * @param $market
     * @param $trade
     * @param $console
     */
    public function __construct($market,$trade,$console,$test = false)
    {
        $this->market = $market;
        $this->trade = $trade;
        $this->console = $console;
        $this->settings = $market['settings'];
        $this->data = $market['data'];
        $this->last_index = count($this->data)-1;
        $this->last_closed_index = count($this->data)-2;
        $this->next = ($this->data[$this->last_index][0] === $trade['t']) ? 0 : 1;
        $this->test = $test;
    }

    /**
     * @return array of markets
     */
    public static function getMarketsForTrade()
    {
        $output = [];
        $markets = Market::where('type','spot')
            ->where(function($q){
                $q->where('is_online',1)
                    ->orWhere('is_trade',1);
            })->get();
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
            $this->old_balance = array_key_exists('old_balance',$this->market) ? $this->market['old_balance'] : floatval($this->settings['start_balance']);
            $this->old_price = array_key_exists('old_price',$this->market) ? $this->market['old_price'] : 0;
        }else{
            $simulation = Simulation::where('market_id',$this->market['id'])->latest('id')->first();
            if($simulation){
                $this->status = $simulation->action === 'buy' ? 'bought' : 'deposit';
                $this->balance = $simulation->result;
                $this->old_balance = $simulation->value;
                $this->old_price = $simulation->price;
            }else{
                $this->status = 'deposit';
                $this->balance = floatval($this->settings['start_balance']);
                $this->old_balance = floatval($this->settings['start_balance']);
                $this->old_price = 0;
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

    function onDeposit($commission,$close)
    {
        $trade_OK = false;
        if($this->console) $this->console->info('market ' . $this->market['id'] . ' $rsi: '.$this->rsi[$this->last_index].
            ' <= '.$this->settings['rsi_min']);
        $this->status = 'bought';
        $this->balance = floatval($this->settings['start_balance']); // stable set amount of quote for buying
        if($this->market['is_trade']){
            try{
                $res = $this->market['api']->marketQuoteBuy($this->market['name'],$this->balance);
                if($this->console)  $this->console->info('market '.$this->market['id'].' buy: ' . json_encode($res));
                $this->balance = 0;
                $commission_sum = 0;
                foreach ($res['fills'] as $fill){
                    $this->balance += floatval($fill['qty']);
                    if(str_contains($this->market['name'], $fill['commissionAsset'])){
                        $commission_sum += floatval($fill['commission']);
                    }
                }
                $this->old_balance = floatval($res['cummulativeQuoteQty']);
                $close = $this->old_balance / $this->balance;
                $this->balance = $this->balance - $commission_sum;

                if(floatval($this->settings['profit_limit']) !== 0.0){
                    $price = $close * (1 + floatval($this->settings['profit_limit']));
                    $res = $this->market['api']->sell($this->market['name'], $this->balance, $price);
                    Order::create([
                        'market_id' => $this->market['id'],
                        'binance_id' => $res['orderId'],
                        'side' => $res['side'],
                        'quantity' => $res['origQty'],
                        'price' => $res['price'],
                        'status' => $res['status']
                    ]);
                }
                $trade_OK = true;
            }catch (\Exception $e){
                if($this->console)  $this->console->info('market '.$this->market['id'].' buy error: ' . $e->getMessage());
                if(str_contains($e->getMessage(), 'MIN_NOTIONAL')){
                    $this->market['mark'] = 'мала сума закупки';
                }elseif(str_contains($e->getMessage(), 'insufficient balance')){
                    $this->market['mark'] = 'мало грошей';
                }else{
                    $this->telegram_log('market '.$this->market['name'].' '.$this->market['id'].' buy error: ' . $e->getMessage());
                }
            }
        }else{
            $this->old_balance = $this->balance;
            $this->balance = $close ? $this->balance / $close : $this->balance;
            $this->balance = floor($this->balance * (1 - $commission) * 10**8) / 10**8;
            $trade_OK = true;
        }
        if($trade_OK && !$this->test){
            Simulation::create([
                'market_id' => $this->market['id'],
                'action' => 'buy',
                'value' => $this->old_balance,
                'result' => $this->balance,
                'price' => $close,
                'rsi' => $this->is_rsi ? $this->rsi[$this->last_index] : 0,
                'stoch_rsi' => $this->is_stoch ? $this->stoch_rsi['stoch_rsi'][$this->last_index] : 0,
                'time' => date("Y-m-d H:i:s",intval($this->trade['T'])/1000)
            ]);
            $this->market['mark'] = 'buy';
        }
        return $trade_OK;
    }

    function onBought($commission,$close,$rsi_sell_rule,$stoch_sell_rule)
    {
        $trade_OK = false;
        if ($this->market['is_trade']) {
            if(floatval($this->settings['profit_limit']) !== 0.0){
                $order = Order::where('market_id',$this->market['id'])
                    ->where('side','SELL')
                    ->whereIn('status',['NEW','PARTIALLY_FILLED'])
                    ->latest()
                    ->first();
                if(!$order){
                    $this->status = 'deposit';
                    $this->balance = floatval($order->quantity) * floatval($order->price);
                    $this->old_balance = floatval($order->quantity);
                    return true;
                }
                $res = $this->market['api']->orderStatus($this->market['name'],$order->binance_id);
                $order->status = $res['status'];
                $order->save();
                if($res['status'] !== 'NEW' && $res['status'] !== 'PARTIALLY_FILLED'){
                    $this->status = 'deposit';
                    $trade_OK = true;
                    if($res['status'] === 'FILLED'){
                        $this->balance = $res['cummulativeQuoteQty'];
                        $this->old_balance = floatval($res['origQty']);
                        $close = floatval($order->price);
                        $this->market['mark'] = 'sell';
                    }else{
                        $this->balance = floatval($order->quantity) * floatval($order->price);
                        $this->old_balance = floatval($order->quantity);
                        $close = floatval($order->price);
                    }
                }
            }elseif($rsi_sell_rule && $stoch_sell_rule) {
                $this->status = 'deposit';
                try {
                    $res = $this->market['api']->marketSell($this->market['name'], $this->balance);
                    if($this->console)  $this->console->info('market ' . $this->market['id'] . ' sell: ' . json_encode($res));
                    $this->balance = 0;
                    $commission_sum = 0;
                    foreach ($res['fills'] as $fill) {
                        $this->balance += floatval($fill['qty']) * floatval($fill['price']);
                        if (str_contains($this->market['name'], $fill['commissionAsset'])) {
                            $commission_sum += floatval($fill['commission']);
                        }
                    }
                    $old_balance = floatval($res['executedQty']);
                    $close = $this->balance / $old_balance;
                    $this->balance = $this->balance - $commission_sum;
                    $trade_OK = true;
                } catch (\Exception $e) {
                    if($this->console)  $this->console->info('market ' . $this->market['id'] . ' sell error: ' . $e->getMessage());
                    if (str_contains($e->getMessage(), 'MIN_NOTIONAL')) {
                        $this->market['mark'] = 'мала сума закупки';
                    } elseif (str_contains($e->getMessage(), 'insufficient balance')) {
                        $this->market['mark'] = 'мало грошей';
                    } else {
                        $this->telegram_log('market ' . $this->market['name'] . ' ' . $this->market['id'] . ' sell error: ' . $e->getMessage());
                    }
                }
            }
        }else{
            $is_profit = !(floatval($this->settings['profit_limit']) == 0.0) && $close > $this->old_price * (1 + floatval($this->settings['profit_limit']));
            if(($rsi_sell_rule && $stoch_sell_rule) || $is_profit) {
                $this->status = 'deposit';
                $trade_OK = true;
                $this->balance = $this->balance * $close;
                $this->balance = floor($this->balance * (1 - $commission) * 10 ** 8) / 10 ** 8;
            }
        }
        if ($trade_OK && !$this->test) {
            Simulation::create([
                'market_id' => $this->market['id'],
                'action' => 'sell',
                'value' => $this->old_balance,
                'result' => $this->balance,
                'price' => $close,
                'rsi' => $this->is_rsi ? $this->rsi[$this->last_index] : 0,
                'stoch_rsi' => $this->is_stoch ? $this->stoch_rsi['stoch_rsi'][$this->last_index] : 0,
                'time' => date("Y-m-d H:i:s", intval($this->trade['T']) / 1000)
            ]);
            $this->market['mark'] = 'sell';
        }
        return $trade_OK;
    }

    function makeTrade()
    {
        $commission = array_key_exists('commission', $this->settings) ? $this->settings['commission'] : 0;
        $close = floatval($this->trade['c']);
        $trade_OK = true;
        $this->old_balance = $this->balance;

        $rsi_buy_rule = !$this->is_rsi || $this->rsi[$this->last_index] <= $this->settings['rsi_min'];
        $rsi_sell_rule = intval($this->settings['rsi_max']) > 0 &&
            (!$this->is_rsi || $this->rsi[$this->last_index] >= $this->settings['rsi_max']);

        $stoch_buy_rule = !$this->is_stoch || $this->stoch_rsi_logic === 'up';
        $stoch_sell_rule = !$this->is_stoch || $this->stoch_rsi_logic === 'down';

        if($this->status == 'deposit' && $rsi_buy_rule && $stoch_buy_rule){
            $trade_OK = $this->onDeposit($commission,$close);
        }elseif($this->status == 'bought') {
            $trade_OK = $this->onBought($commission,$close,$rsi_sell_rule,$stoch_sell_rule);
        }
        if($trade_OK){
            $this->market['status'] = $this->status;
            $this->market['balance'] = $this->balance;
            $this->market['old_balance'] = $this->old_balance;
            $this->market['old_price'] = $close;
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

//        2) Робити аналіз покупки чи продажу. Якщо є покупка чи продаж, але не створилася нова свічка, тоді треба зберегти $mark в $market. Якщо створилася нова свічка то скинути $market['mark'] на false, і записати в базу теперішній $mark.
        $this->makeTrade();

        if($this->next && !$this->test){
            $mark = $this->market['mark'];
            $this->market['mark'] = false;

            $market_db = Market::find($this->market['id']);
            $market_db->charts()->create([
                'type' => 'data',
                'time' => $this->data[$this->last_closed_index][6],
                'data' => ['c' => $this->data[$this->last_closed_index], 'm' => $mark]
            ]);
            if(!empty($this->rsi)){
                $market_db->charts()->create([
                    'type' => 'rsi',
                    'time' => $this->data[$this->last_closed_index][6],
                    'data' => $this->rsi[$this->last_closed_index]
                ]);
            }
            if(!empty($this->stoch_rsi)){
                $market_db->charts()->create([
                    'type' => 'stoch_rsi',
                    'time' => $this->data[$this->last_closed_index][6],
                    'data' => $this->stoch_rsi['stoch_rsi'][$this->last_closed_index]
                ]);
                $market_db->charts()->create([
                    'type' => 'sma_stoch_rsi',
                    'time' => $this->data[$this->last_closed_index][6],
                    'data' => $this->stoch_rsi['sma_stoch_rsi'][$this->last_closed_index]
                ]);
            }
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
