<?php

namespace App\Http\Controllers;

use App\Helpers\Intervals;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $markets = Auth::user()->markets()->where('type','spot')->where('is_trade',0)->get();
        return view('home',[
            'markets' => $markets
        ]);
    }
}
