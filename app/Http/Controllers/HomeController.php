<?php

namespace App\Http\Controllers;

use App\Helpers\Intervals;
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
        $candle_intervals = Intervals::titles();
        return view('home',[
            'markets' => $markets,
            'candle_intervals' => $candle_intervals
        ]);
    }
}
