<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Midtrans\Midtrans;

Route::post('/extensions/midtrans/webhook', [Midtrans::class, 'webhook'])->withoutMiddleware([VerifyCsrfToken::class])->name('extensions.gateways.midtrans.webhook');
