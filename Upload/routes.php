<?php

use Illuminate\Support\Facades\Route;

Route::post('/midtrans/webhook', [App\Extensions\Gateways\Midtrans\Midtrans::class, 'webhook'])->name('midtrans.webhook');
