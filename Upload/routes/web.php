<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Midtrans\Midtrans;

Route::post('/extensions/midtrans/webhook', [Midtrans::class, 'webhook'])->name('extensions.gateways.midtrans.webhook');
