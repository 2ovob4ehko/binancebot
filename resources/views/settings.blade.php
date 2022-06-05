@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col">
            <div class="card">
                <div class="card-header">Налаштування</div>
                <div class="card-body">
                    <form action="/settings" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="mx-2">API key</div>
                                    <div class="col">
                                        <input type="text" name="api_key"
                                               value="{{\Illuminate\Support\Facades\Auth::user()->setting('api_key')->value ?? ''}}" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="mx-2">Secret key</div>
                                    <div class="col">
                                        <input type="text" name="secret_key"
                                               value="{{\Illuminate\Support\Facades\Auth::user()->setting('secret_key')->value ?? ''}}" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-success btn-sm mt-3" type="submit">Зберегти</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
