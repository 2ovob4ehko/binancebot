<?php

namespace App\Http\Controllers;


use App\Helpers\Analysis;
use App\Helpers\Intervals;
use App\Models\Market;

class TradeController extends Controller
{
    public static function getMarketsForTrade()
    {
        $output = [];
        $markets = Market::where('is_online',1)->get();
        $analysis = new Analysis();
        if($markets->count() > 0){
            foreach ($markets as $market){
                $settings = $market->settings;
                $candles = MarketController::multilimitQuery($market->name,$settings['candle'],100);
                $closed = array_map(function($el){return floatval($el[4]);}, $candles);
                $rsi = $analysis->rsi($closed,$settings['rsi_period']);
                if(!empty($settings['stoch_rsi_period'])){
                    $stoch_rsi = $analysis->stoch_rsi($rsi,$settings['rsi_period'],$settings['stoch_rsi_period']);
                }else{
                    $stoch_rsi = [];
                }
                $output[$market->name] = [
                    'id' => $market->id,
                    'name' => $market->name,
                    'settings' => $market->settings,
                    'data' => $candles,
                    'rsi' => $rsi,
                    'stoch_rsi' => $stoch_rsi,
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
//        TODO:
//        1) Визначити кількість мілісекунд в розмірі свічки
        $candle_time = Intervals::ms()[$market['settings']['candle']];
//        2) Взяти час відкриття останньої свічки і час торгівлі, порівняти чи різниця між ними не перевищує розмір свічки

//        2.1) Якщо не перевищує - додати торгівлю до свічки
//        2.2) Якщо перевищує - закрити свічку цією ціною і відкрити нову свічку цією ціною. Відняти першу свічку масива.
//        3а) Спробувати провести аналіз свічок, не дивлячись на те, чи свічка закрилася чи ні
//        3б) Провести аналіз свічок, якщо свічка закрилася.
//        4) Після аналізу додати до історичних даних нову закриту свічку і її аналіз.
    }
}
