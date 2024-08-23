<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('user', function () {
    return view('sol');
});
Route::post('/create-user', [SolController::class, 'store']);
Route::get('/users', [SolController::class, 'index']);
Route::post('/set-current-user', [SolController::class, 'setCurrentUser']);
Route::post('/get-balance', [SolController::class, 'getBalance']);
Route::post('/withdraw', [SolController::class, 'withdraw']);
Route::post('/history', [SolController::class, 'getHistory']);
Route::get('/test-webhook', [SolController::class, 'testWebhook']);
Route::get('/test', [SolController::class, 'test']);