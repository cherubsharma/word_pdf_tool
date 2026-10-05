<?php

use App\Http\Controllers\DocumentConversionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/convert', [DocumentConversionController::class, 'convert'])->name('document.convert');
Route::get('/convert/status', [DocumentConversionController::class, 'status'])->name('document.status');

