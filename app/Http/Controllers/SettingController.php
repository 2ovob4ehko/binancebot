<?php

namespace App\Http\Controllers;

use App\Helpers\Intervals;
use App\Models\Market;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
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
        return view('settings');
    }

    public function save(Request $request)
    {
        $params = ['api_key','secret_key'];
        foreach ($params as $param){
            $setting = Setting::updateOrCreate(
                ['user_id' => Auth::user()->id, 'name' => $param],
                ['value' => $request->{$param} ?? '']
            );
        }
        return redirect('/settings')->with('message', 'Налаштування збережено');
    }
}
