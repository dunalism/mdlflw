<?php

use Illuminate\Support\Facades\Route;

Route::get('/favicon.ico', function () {
    return response()->file(public_path('favicon.ico'));
});
