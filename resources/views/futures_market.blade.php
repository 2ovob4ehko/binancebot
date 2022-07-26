@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col">
            <div class="card">
                <div class="card-header">Маркет</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <div id="marketTable">
                            @if(!empty($market))
                                <form action="/futures_market" method="POST">
                                    @csrf
                                    @if($market->id)
                                        <input type="hidden" name="id" value="{{$market->id}}">
                                    @endif
                                    <div class="row mx-0">
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Лонг маркет</div>
                                                <div class="col">
                                                    <input type="text" name="name" value="{{$market->name}}" class="form-control form-control-sm" placeholder="Назва">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Лонг баланс</div>
                                                <div class="col">
                                                    <input type="text" name="long_balance" value="{{$market->settings['long_balance'] ?? ''}}" class="form-control form-control-sm" placeholder="100">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Лонг плече</div>
                                                <div class="col">
                                                    <input type="text" name="long_leverage" value="{{$market->settings['long_leverage'] ?? ''}}" class="form-control form-control-sm" placeholder="1">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Шорт маркет</div>
                                                <div class="col">
                                                    <input type="text" name="short_market" value="{{$market->settings['short_market'] ?? ''}}" class="form-control form-control-sm" placeholder="Назва">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Шорт баланс</div>
                                                <div class="col">
                                                    <input type="text" name="short_balance" value="{{$market->settings['short_balance'] ?? ''}}" class="form-control form-control-sm" placeholder="10">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Шорт плече</div>
                                                <div class="col">
                                                    <input type="text" name="short_leverage" value="{{$market->settings['short_leverage'] ?? ''}}" class="form-control form-control-sm" placeholder="15">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1 d-flex align-items-center">
                                            <div class="d-flex align-items-center">
                                                <label class="mx-2" for="is_online_{{$market->id}}">Онлайн тестова торгівля</label>
                                                <div class="col">
                                                    <input type="checkbox" id="is_online_{{$market->id}}" name="is_online" value="yes" class="form-check-input" {{$market->is_online ? 'checked' : ''}}>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1 d-flex align-items-center">
                                            <div class="d-flex align-items-center">
                                                <label class="mx-2" for="is_trade_{{$market->id}}">Онлайн торгівля</label>
                                                <div class="col">
                                                    <input type="checkbox" id="is_trade_{{$market->id}}" name="is_trade" value="yes" class="form-check-input" {{$market->is_trade ? 'checked' : ''}}>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr/>
                                    <div class="row mx-0">
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Вхід для лонг 24h +</div>
                                                <div class="col">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="h24_long" value="{{$market->settings['h24_long'] ?? ''}}" class="form-control" placeholder="1">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Лонг профіт</div>
                                                <div class="col">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="long_profit" value="{{$market->settings['long_profit'] ?? ''}}" class="form-control" placeholder="4">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Лонг стоп лосс</div>
                                                <div class="col">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="long_loss" value="{{$market->settings['long_loss'] ?? ''}}" class="form-control" placeholder="2">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Шорт профіт</div>
                                                <div class="col">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="short_profit" value="{{$market->settings['short_profit'] ?? ''}}" class="form-control" placeholder="7">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr/>
                                    <div class="row mx-0 mb-3">
                                        <div class="col-12 p-1">
                                            <button class="btn btn-success btn-sm" type="submit" title="Зберегти"><i class="fa-solid fa-floppy-disk"></i></button>
                                            @if($market->settings)
                                                @if(!$market->is_online && !$market->is_trade)
                                                    <button class="btn btn-primary btn-sm startMarketAnalysis1" title="Запустити симуляцію" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-play"></i></button>
                                                @endif
                                            <button class="btn btn-secondary btn-sm toggleAnalysis" title="Показати результат" type="button"><i class="fa-solid fa-chart-column"></i></button>
                                            <button class="btn btn-danger btn-sm delete_market" title="Видалити" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-trash-can"></i></button>
                                            @endif
                                        </div>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
