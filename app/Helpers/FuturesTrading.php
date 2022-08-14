<?php

namespace App\Helpers;

use App\BinanceSDK\BinanceSDK;
use App\Models\Market;
use App\Models\Simulation;

class FuturesTrading
{
    public $market;
    public $console;
    public $ticker;
    public $settings;
    public $status;
    public $old_price;

    // <symbol>@ticker
    // "P": "250.00",      // Price change percent
    // "c": "0.0025",      // Last price

    /*
     * TODO:
     * 1) Якщо це покупка Зробити запит на 24 %
     * 1.1) Якщо вище за норму (1%) тоді купуємо лонг (х1)
     * 1.2) Якщо купили Виставити лімітний ордер по тейк профіту (4%) від ціни покупки
     * 2) Якщо це продаж Перевіряємо чи ордер ще відкритий
     * 2.1) Якщо закритий Переходимо до покупки
     * 2.2) Якщо відкритий Перевіряємо на скільки змінилася ціна теперішня в порівнянні з відкритим ордером головним
     * 2.2.1) Якщо ціна впала нижче (2%) Купуємо другорядний шорт (х15)
     * 2.2.2) Якщо ціна впала нижче (7%) Продаємо лонг і Продаємо шорт
     */

    /**
     * Trading constructor.
     * @param $market
     * @param $ticker
     * @param $console
     */
    public function __construct($market,$ticker,$console)
    {
        $this->market = $market;
        $this->ticker = $ticker;
        $this->console = $console;
        $this->settings = $market['settings'];
    }

    /**
     * @return array of markets
     */
    public static function getMarketsForTrade()
    {
        $output = [];
        $markets = Market::where('type','futures')
            ->where(function($q){
                $q->where('is_online',1)
                    ->orWhere('is_trade',1);
            })->get();
        if($markets->count() > 0){
            foreach ($markets as $market){
                $settings = $market->settings;

                $user = $market->user;
                $api_key = $user->setting('api_key')->value ?? '';
                $secret_key = $user->setting('secret_key')->value ?? '';
                $api = new BinanceSDK($api_key, $secret_key);
                $api->caOverride = true;
                $output[$market->id] = [
                    'id' => $market->id,
                    'name' => $market->name,
                    'settings' => $market->settings,
                    'is_trade' => $market->is_trade,
                    'api' => $api
                ];
            }
        }
        return $output;
    }

    public function addNewPrice()
    {
        $this->getLastBalance();
        $this->checkPrecision();

        if($this->status == 'sell'){ // need to buy
            $this->makeBuy();
        }elseif($this->status == 'buy'){ // need to sell

        }

        return $this->market;
    }

    function makeBuy()
    {
        $trade_OK = false;
        if(floatval($this->ticker['P']) >= floatval($this->settings['h24_long'])){
            // Купити лонг х1
            // Створити лімітний ордер на +4% від ціни покупки
            if($this->market['is_trade']){
                try{
                    $this->market['api']->leverageFuture($this->market['name'],intval($this->settings['long_leverage']));
                    $this->market['api']->marginTypeFuture($this->market['name'],true);
                    //$res = $this->market['api']->marginTypeFuture($this->market['name'],true);

                    $trade_OK = true;
                }catch (\Exception $e){
                    $this->console->info('market '.$this->market['id'].' buy error: ' . $e->getMessage());
                }
            }else{
                $trade_OK = true;
            }
        }
        return $trade_OK;
    }

    function getLastBalance()
    {
        if(array_key_exists('status',$this->market)){
            $this->status = $this->market['status'];
            $this->old_price = array_key_exists('old_price',$this->market) ? $this->market['old_price'] : 0;
        }else{
            $simulation = Simulation::where('market_id',$this->market['id'])->latest('id')->first();
            if($simulation){
                $this->status = $simulation->action;
                $this->old_price = $simulation->price;
            }else{
                $this->status = 'buy';
                $this->old_price = 0;
            }
        }
    }

    function checkPrecision()
    {
        if(!array_key_exists('baseAsset',$this->settings) || !$this->settings['baseAsset']){
            $info = $this->market['api']->exchangeInfo()['symbols'][$this->market['name']];
            $info_short = $this->market['api']->exchangeInfo()['symbols'][$this->settings['short_market']];
            $this->settings['baseAsset'] = $info['baseAsset'];
            $this->settings['baseAssetPrecision'] = $info['baseAssetPrecision'];
            $this->settings['quoteAsset'] = $info['quoteAsset'];
            $this->settings['quoteAssetPrecision'] = $info['quoteAssetPrecision'];
            $this->settings['quoteAssetShort'] = $info_short['quoteAsset'];
            $this->settings['quoteAssetPrecisionShort'] = $info_short['quoteAssetPrecision'];
            $this->market['settings'] = $this->settings;
            $market_db = Market::find($this->market['id']);
            $market_db->update([
                'settings' => $this->settings,
            ]);
        }
    }
}
