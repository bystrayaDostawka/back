<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderStatusController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Controllers\Api\AuthController;

Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');

// Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
//     return $request->user();
// });

// Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
//     $request->user()->currentAccessToken()->delete();
//     return response()->json(['message' => 'Выход выполнен']);
// });

Route::middleware('auth:api')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('banks', BankController::class);
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('order-statuses', OrderStatusController::class);
    Route::patch('orders/{order}/status', [OrderController::class, 'changeStatus']);
    Route::delete('orders/bulk', [OrderController::class, 'bulkDestroy']);
    Route::post('/orders/bulk-update', [OrderController::class, 'bulkUpdate']);
    Route::post('orders/import-excel', [\App\Http\Controllers\Api\OrderController::class, 'importFromExcel']);
    Route::get('banks/{id}/activity-log', [\App\Http\Controllers\Api\BankController::class, 'activityLog']);
    Route::get('orders/{id}/activity-log', [\App\Http\Controllers\Api\OrderController::class, 'activityLog']);
    Route::get('order-statuses/{id}/activity-log', [\App\Http\Controllers\Api\OrderStatusController::class, 'activityLog']);
    Route::get('users/{id}/activity-log', [\App\Http\Controllers\Api\UserController::class, 'activityLog']);
    Route::get('activity-logs/batch', [\App\Http\Controllers\Api\ActivityLogController::class, 'batch']);
    
    // Статистика
    Route::get('statistics/orders', [StatisticsController::class, 'getOrderStatistics']);
    Route::get('statistics/couriers', [StatisticsController::class, 'getCourierStatistics']);
    Route::get('statistics/dashboard', [StatisticsController::class, 'getDashboardStats']);
});
