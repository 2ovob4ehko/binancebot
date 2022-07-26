<?php

namespace App\Http\Controllers;

use App\Helpers\Analysis;
use App\Helpers\Trading;
use App\Helpers\Intervals;
use App\Jobs\UploadCSVFromBinance;
use App\Models\Market;
use App\Models\Simulation;
use Binance\API;
use Carbon\CarbonPeriod;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FutureMarketController extends Controller
{
    public function index()
    {
        $markets = Auth::user()->markets()->where('type','futures')->get();
        return view('futures_list',[
            'markets' => $markets
        ]);
    }

    public function show($id,Request $request)
    {
        if($id){
            if(!Auth::user()->markets->contains($id)) return redirect('/');
            $market = Market::findOrFail($id);
        }else{
            $market = new Market();
        }
        return view('futures_market',[
            'market' => $market,
        ]);
    }

    public function save(Request $request)
    {
        $name = strtoupper(preg_replace('/[^\w]/', '', $request->name));
        if($request->has('id')){
            if(!Auth::user()->markets->contains($request->id)) return redirect('/');
            Market::where('id',$request->id)->update([
                'name' => $name,
                'settings' => [
                    'long_balance' => $request->long_balance,
                    'long_leverage' => $request->long_leverage,
                    'short_market' => $request->short_market,
                    'short_balance' => $request->short_balance,
                    'short_leverage' => $request->short_leverage,
                    'h24_long' => $request->h24_long,
                    'long_profit' => $request->long_profit,
                    'long_loss' => $request->long_loss,
                    'short_profit' => $request->short_profit,
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
                    'long_balance' => $request->long_balance,
                    'long_leverage' => $request->long_leverage,
                    'short_market' => $request->short_market,
                    'short_balance' => $request->short_balance,
                    'short_leverage' => $request->short_leverage,
                    'h24_long' => $request->h24_long,
                    'long_profit' => $request->long_profit,
                    'long_loss' => $request->long_loss,
                    'short_profit' => $request->short_profit,
                ],
                'is_online' => $request->has('is_online'),
                'is_trade' => $request->has('is_trade'),
                'type' => 'futures'
            ]);
            $id = $market->id;
        }
        return redirect('/futures_market/'.$id)->with('message', 'Налаштування збережено');
    }
}
