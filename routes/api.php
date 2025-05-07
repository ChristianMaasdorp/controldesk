<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/api/tickets',[\App\Http\Controllers\Api\TicketController::class,'store'])->name('api.tickets.store');
Route::get('/api/tickets/{id}',[\App\Http\Controllers\Api\TicketController::class,'get'])->name('api.tickets.get');
