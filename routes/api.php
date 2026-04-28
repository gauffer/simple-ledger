<?php

use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::post('/withdrawals', [WithdrawalController::class, 'store']);
