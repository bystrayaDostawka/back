<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderStatusController;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Route::post('/login', function (Request $request) {
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);
    $user = User::where('email', $request->email)->first();
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Неверный логин или пароль'], 401);
    }
    $token = $user->createToken('api_token')->plainTextToken;
    return response()->json([
        'token' => $token,
        'user'  => $user,
    ]);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Выход выполнен']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('banks', BankController::class);
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('order-statuses', OrderStatusController::class);
    Route::patch('orders/{order}/status', [OrderController::class, 'changeStatus']);
    Route::delete('orders/bulk', [OrderController::class, 'bulkDestroy']);
    Route::post('/orders/bulk-update', [OrderController::class, 'bulkUpdate']);
});
