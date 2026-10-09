<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;

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

// Public routes (if any)
// Route::post('/login', [AuthController::class, 'login']);

Route::prefix('tickets')->group(function () {
    Route::post('/', [TicketController::class, 'store'])->name('api.tickets.store');

    Route::middleware('tickets.api')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('api.tickets.index');
        Route::get('/{id}', [TicketController::class, 'get'])->name('api.tickets.get');
    });
});
