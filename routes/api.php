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

// Protected routes
Route::prefix('tickets')->group(function () {
    Route::post('/', [TicketController::class, 'store'])->name('api.tickets.store');
    Route::get('/{id}', [TicketController::class, 'get'])->name('api.tickets.get');
});
