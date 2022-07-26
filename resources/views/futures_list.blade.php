@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col">
            <div class="card">
                <div class="card-header">Маркети Ф'ючерси <b>(Ще не працює)</b></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <div id="marketTable" class="table_striped">
                            @if(!empty($markets) && $markets->count())
                                @foreach($markets as $index=>$market)
                                    <div class="market_row d-flex align-items-center mx-0">
                                        <div class="flex-1 p-1">
                                            <b>{{$market->name}}</b>
                                            @if(!empty($market->settings['long_balance']))
                                                | <i>Лонг баланс:</i>&nbsp;<b>{{$market->settings['long_balance']}}</b>
                                            @endif
                                            @if(!empty($market->settings['long_leverage']))
                                                | <i>Лонг плече:</i>&nbsp;<b>{{$market->settings['long_leverage']}}</b>
                                            @endif
                                            @if(!empty($market->settings['short_market']))
                                                | <i>Шорт маркет:</i>&nbsp;<b>{{$market->settings['short_market']}}</b>
                                            @endif
                                            @if(!empty($market->settings['short_balance']))
                                                | <i>Шорт баланс:</i>&nbsp;<b>{{$market->settings['short_balance']}}</b>
                                            @endif
                                            @if(!empty($market->settings['short_leverage']))
                                                | <i>Шорт плече:</i>&nbsp;<b>{{$market->settings['short_leverage']}}</b>
                                            @endif
                                            @if(!empty($market->settings['h24_long']))
                                                | <i>Вхід лонг 24h:</i>&nbsp;<b>+{{$market->settings['h24_long']}}%</b>
                                            @endif
                                            @if(!empty($market->settings['long_profit']))
                                                | <i>Лонг профіт:</i>&nbsp;<b>{{$market->settings['long_profit']}}%</b>
                                            @endif
                                            @if(!empty($market->settings['long_loss']))
                                                | <i>Лонг лосс:</i>&nbsp;<b>{{$market->settings['long_loss']}}%</b>
                                            @endif
                                            @if(!empty($market->settings['short_profit']))
                                                | <i>Шорт профіт:</i>&nbsp;<b>{{$market->settings['short_profit']}}%</b>
                                            @endif
                                        </div>
                                        <div class="ms-auto">
                                            @if($market->is_online || $market->is_trade)
                                                <i class="fa-solid fa-money-bill-trend-up"></i>
                                            @endif
{{--                                            @if($market->upload_status === 'uploading')--}}
{{--                                                <span class="badge bg-secondary">База завантажується</span>--}}
{{--                                            @elseif($market->upload_status === 'uploaded')--}}
{{--                                                <span class="badge bg-secondary">База завантажена</span>--}}
{{--                                            @else--}}
{{--                                                <button class="btn btn-secondary btn-sm upload_db m-1" data-market="{{$market->name}}" title="Завантажити базу" type="button"><i class="fa-solid fa-download"></i></button>--}}
{{--                                            @endif--}}
                                            <a href="/futures_market/{{$market->id}}" target="_blank" class="btn btn-primary btn-sm m-1" type="button"><i class="fa-solid fa-pen"></i></a>
                                            <button class="btn btn-danger btn-sm delete m-1" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <a href="/futures_market/0" target="_blank" class="btn btn-primary btn-sm mt-3">Додати новий</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
