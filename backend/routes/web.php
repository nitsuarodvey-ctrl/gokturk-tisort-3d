<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'GUB Merch API', 'status' => 'ok']);
});
