@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col">
            <div class="card">
                <div class="card-header">Маркети</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <div id="marketTable" class="table_striped">
                            @if(!empty($markets) && $markets->count())
                                @foreach($markets as $index=>$market)
                                    <div class="market_row d-flex align-items-center mx-0">
                                        <div class="flex-1 p-1">
                                            <b>{{$market->name}}</b>
                                            @if(!empty($market->settings['candle']))
                                                | <i>свічка:</i>&nbsp;<b>{{$market->settings['candle']}}</b>
                                            @endif
                                            @if(!empty($market->settings['period']))
                                                | <i>днів:</i>&nbsp;<b>{{$market->settings['period']}}</b>
                                            @endif
                                            @if(!empty($market->settings['rsi_period']))
                                                | <i>RSI&nbsp;інтервал:</i>&nbsp;<b>{{$market->settings['rsi_period']}}</b>
                                            @endif
                                            @if(!empty($market->settings['stoch_rsi_period']))
                                                | <i>Stoch&nbsp;RSI&nbsp;інтервал:</i>&nbsp;<b>{{$market->settings['stoch_rsi_period']}}</b>
                                            @endif
                                            @if(!empty($market->settings['rsi_min']))
                                                | <i>RSI&nbsp;min:</i>&nbsp;<b>{{$market->settings['rsi_min']}}</b>
                                            @endif
                                            @if(!empty($market->settings['rsi_max']))
                                                | <i>RSI&nbsp;max:</i>&nbsp;<b>{{$market->settings['rsi_max']}}</b>
                                            @endif
                                            @if(!empty($market->settings['profit_limit']))
                                                | <i>межа&nbsp;прибутку:</i>&nbsp;<b>{{$market->settings['profit_limit']}}</b>
                                            @endif
                                            @if(!empty($market->settings['start_balance']))
                                                | <i>баланс:</i>&nbsp;<b>{{$market->settings['start_balance']}}</b>
                                            @endif
                                        </div>
                                        <div class="ms-auto flex-shrink-0">
                                            @if($market->is_online || $market->is_trade)
                                                <i class="fa-solid fa-money-bill-trend-up"></i>
                                            @endif
                                            @if($market->upload_status === 'uploading')
                                                <span class="badge bg-secondary">База завантажується</span>
                                            @elseif($market->upload_status === 'uploaded')
                                                <span class="badge bg-secondary">База завантажена</span>
                                            @else
                                                <button class="btn btn-secondary btn-sm upload_db m-1" data-market="{{$market->name}}" title="Завантажити базу" type="button"><i class="fa-solid fa-download"></i></button>
                                            @endif
                                            <a href="/market/{{$market->id}}" target="_blank" class="btn btn-primary btn-sm m-1" type="button"><i class="fa-solid fa-pen"></i></a>
                                            <button class="btn btn-danger btn-sm delete m-1" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <a href="/market/0" target="_blank" class="btn btn-primary btn-sm mt-3">Додати новий</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
