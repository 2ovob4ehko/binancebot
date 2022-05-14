<?php

namespace App\Http\Controllers;

use App\Models\Market;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $markets = Market::all();
        $candle_intervals = ['1m','3m','5m','15m','30m','1h','2h','4h','6h','8h','12h','1d'];
        return view('home',[
            'markets' => $markets,
            'candle_intervals' => $candle_intervals
        ]);
    }
}
