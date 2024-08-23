<?php

use Illuminate\Support\Facades\Route;
use Hoangnh283\Solana\Http\controllers\SolanaController;
use Hoangnh283\Solana\Http\controllers\SolanaNotificationController;
Route::get('/test_package', function (){
    return 'Solana test package';
});
Route::get('/solana/test', [SolanaController::class, 'test']);

Route::post('/solana/create-address', [SolanaController::class, 'createAddress']);
Route::post('/solana/deposit', [SolanaController::class, 'deposit']);
Route::post('/solana/withdraw', [SolanaController::class, 'withdraw']);
Route::post('/solana/transfer', [SolanaController::class, 'transfer']);
Route::post('/solana/signatures_address', [SolanaController::class, 'getSignaturesForAddress']);
Route::post('/solana/airdrop', [SolanaController::class, 'requestAirdrop']);

