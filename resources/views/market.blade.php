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
                                <form action="/market" method="POST">
                                    @csrf
                                    @if($market->id)
                                        <input type="hidden" name="id" value="{{$market->id}}">
                                    @endif
                                    <div class="row mx-0">
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Назва</div>
                                                <div class="col">
                                                    <input type="text" name="name" value="{{$market->name}}" class="form-control form-control-sm" placeholder="Назва">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Свічка</div>
                                                <div class="col">
                                                    <select name="candle" class="form-control form-control-sm">
                                                        @if(!empty($candle_intervals))
                                                            @foreach($candle_intervals as $item)
                                                                <option @if($market->settings && $item === $market->settings['candle']) selected @endif value="{{$item}}">{{$item}}</option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Днів</div>
                                                <div class="col">
                                                    <input type="text" name="period" value="{{$market->settings['period'] ?? ''}}" class="form-control form-control-sm" placeholder="Днів">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Баланс</div>
                                                <div class="col">
                                                    <input type="text" name="start_balance" value="{{$market->settings['start_balance'] ?? ''}}" class="form-control form-control-sm" placeholder="Баланс">
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
                                                <div class="mx-2">RSI інтервал</div>
                                                <div class="col">
                                                    <input type="text" name="rsi_period" value="{{$market->settings['rsi_period'] ?? ''}}" class="form-control form-control-sm" placeholder="RSI інтервал">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Stoch RSI інтервал</div>
                                                <div class="col">
                                                    <input type="text" name="stoch_rsi_period" value="{{$market->settings['stoch_rsi_period'] ?? ''}}" class="form-control form-control-sm" placeholder="Stoch RSI інтервал">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">RSI min</div>
                                                <div class="col">
                                                    <input type="text" name="rsi_min" value="{{$market->settings['rsi_min'] ?? ''}}" class="form-control form-control-sm" placeholder="RSI min">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">RSI max</div>
                                                <div class="col">
                                                    <input type="text" name="rsi_max" value="{{$market->settings['rsi_max'] ?? ''}}" class="form-control form-control-sm" placeholder="RSI max">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Межа прибутку</div>
                                                <div class="col">
                                                    <input type="text" name="profit_limit" value="{{$market->settings['profit_limit'] ?? ''}}" class="form-control form-control-sm" placeholder="Межа прибутку">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1 d-flex align-items-center">
                                            <div class="d-flex align-items-center">
                                                <label class="mx-2" for="stop_on_down_{{$market->id}}">Зупинити купівлю при падінні</label>
                                                <div class="col">
                                                    <input type="checkbox" id="stop_on_down_{{$market->id}}" name="stop_on_down" value="yes" class="form-check-input" {{$market->settings['stop_on_down'] ?? '' ? 'checked' : ''}}>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr/>
                                    <div class="row mx-0">
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Перший розмір дозакупки</div>
                                                <div class="col">
                                                    <input type="text" name="buy_again_amount" value="{{$market->settings['buy_again_amount'] ?? ''}}" class="form-control form-control-sm input_width_limit" placeholder="10">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Перша ціна дозакупки (коефіцієнт зниження ціни)</div>
                                                <div class="col">
                                                    <input type="text" name="buy_again_lower" value="{{$market->settings['buy_again_lower'] ?? ''}}" class="form-control form-control-sm input_width_limit" placeholder="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Ліміт дозакупок (максимальна кількість)</div>
                                                <div class="col">
                                                    <input type="text" name="buy_again_count_limit" value="{{$market->settings['buy_again_count_limit'] ?? ''}}" class="form-control form-control-sm input_width_limit" placeholder="5">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Прогресія ціни дозакупок (коефіцієнт зміни коефіцієнта зниження ціни)</div>
                                                <div class="col">
                                                    <input type="text" name="buy_again_lower_progress" value="{{$market->settings['buy_again_lower_progress'] ?? ''}}" class="form-control form-control-sm input_width_limit" placeholder="1.5">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-4 p-1">
                                            <div class="d-flex align-items-center">
                                                <div class="mx-2">Прогресія розміру дозакупок (1 - без зміни)</div>
                                                <div class="col">
                                                    <input type="text" name="buy_again_amount_progress" value="{{$market->settings['buy_again_amount_progress'] ?? ''}}" class="form-control form-control-sm input_width_limit" placeholder="1.5">
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
                                                    <button class="btn btn-primary btn-sm startMarketAnalysis" title="Запустити симуляцію" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-play"></i></button>
                                                @endif
                                            <button class="btn btn-secondary btn-sm toggleAnalysis" title="Показати результат" type="button"><i class="fa-solid fa-chart-column"></i></button>
                                            <button class="btn btn-secondary btn-sm clear_market" title="Очистити історію" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-broom"></i></button>
                                            <button class="btn btn-danger btn-sm delete_market" title="Видалити" data-id="{{$market->id}}" type="button"><i class="fa-solid fa-trash-can"></i></button>
                                            @endif
                                        </div>
                                    </div>
                                </form>
                                @if($market->settings)
                                    <div class="analysis_list_wrapper opened"
                                         data-group="market-{{$market->id}}"
                                         data-min="{{$market->settings['rsi_min']}}"
                                         data-max="{{$market->settings['rsi_max']}}"
                                         data-data='{{json_encode($market->chartsData())}}'
                                         data-rsi='{{json_encode($market->chartsRsi())}}'
                                         data-stochrsi='{{json_encode($market->chartsStochRsi())}}'
                                         data-smastochrsi='{{json_encode($market->chartsSmaStochRsi())}}'>
                                        <div class="mb-3">
                                            Графік
                                            <a href="{{ route('market', ['id' => $market->id, 'page' => request()->input('page')+1]) }}" class="btn btn-secondary btn-sm">Назад</a>
                                            @if(request()->input('page') > 1)
                                                <a href="{{ route('market', ['id' => $market->id, 'page' => request()->input('page')-1]) }}" class="btn btn-secondary btn-sm">Вперед</a>
                                            @endif
                                        </div>
                                        <div class="row">
                                            <div class="col-3"><b>Результат</b></div>
                                            <div class="col-9">{{$market->result}}</div>
                                        </div>
                                        <div class="candleChart"></div>
                                        <div class="rsiChart"></div>
                                        <div class="stochRsiChart"></div>
                                        @if(!empty($market->simulations) && $market->simulations->count())
                                            <div class="row mx-0">
                                                <div class="col p-1 bg-light"><b>Дія</b></div>
                                                <div class="col p-1 bg-light"><b>Сума до</b></div>
                                                <div class="col p-1 bg-light"><b>Сума після</b></div>
                                                <div class="col p-1 bg-light"><b>Ціна</b></div>
                                                <div class="col p-1 bg-light"><b>RSI</b></div>
                                                <div class="col p-1 bg-light"><b>Час</b></div>
                                            </div>
                                            <div class="table_striped">
                                            @foreach($market->simulations as $sim)
                                                <div class="row mx-0">
                                                    <div class="col p-1">{{$sim->action === 'buy' ? 'Купівля' : 'Продаж'}}</div>
                                                    <div class="col p-1">{{$sim->value}}</div>
                                                    <div class="col p-1">{{$sim->result}}</div>
                                                    <div class="col p-1">{{$sim->price}}</div>
                                                    <div class="col p-1">{{$sim->rsi}}</div>
                                                    <div class="col p-1">{{$sim->time}}</div>
                                                </div>
                                            @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
