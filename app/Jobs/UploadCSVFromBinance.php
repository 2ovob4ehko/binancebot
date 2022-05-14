<?php

namespace App\Jobs;

use App\Helpers\Intervals;
use App\Models\Market;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Zip;

class UploadCSVFromBinance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $market;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($market)
    {
        $this->market = $market;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $candle_intervals = Intervals::titles();
        $periods = array_reverse(CarbonPeriod::create('2020-01-01', 'yesterday')->toArray());
        foreach ($candle_intervals as $candle_interval){
            foreach ($periods as $dt){
                $date = $dt->toDateString();
                $filename = $this->market.'/'.$candle_interval.'/'.$this->market.'-'.$candle_interval.'-'.$date.'.csv';
                if(Storage::disk('local')->exists($filename)) break;
                $url = 'https://data.binance.vision/data/spot/daily/klines/'.$this->market.'/'.$candle_interval.'/'.$this->market.'-'.$candle_interval.'-'.$date.'.zip';
                $response = Http::get($url);
                Storage::disk('local')->put('temp.zip', $response->body());
                try{
                    $zip = Zip::open(storage_path('app/temp.zip'));
                    $zip->extract(storage_path('app/'.$this->market.'/'.$candle_interval));
                }catch (\Exception $e){
                    break;
                }
            }
        }
        Market::where('name',$this->market)->update(['upload_status' => 'uploaded']);
    }
}
