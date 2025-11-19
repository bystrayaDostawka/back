<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderStatusController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\OrderPhotoController;
use App\Http\Controllers\Api\OrderFileController;
use App\Http\Controllers\Api\OrderCommentController;
use App\Http\Controllers\Api\AgentReportController;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileAuthController;

Route::post('login', [AuthController::class, 'login']);
Route::post('mobile/login', [MobileAuthController::class, 'login']);

// Добавляем GET маршрут login для обработки ошибок аутентификации
Route::get('login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('api.login');
Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('mobile/logout', [MobileAuthController::class, 'logout'])->middleware('auth:api');
Route::get('mobile/me', [MobileAuthController::class, 'me'])->middleware('auth:api');
Route::post('mobile/push-token', [AuthController::class, 'updatePushToken'])->middleware('auth:api');
Route::get('mobile/notifications', [AuthController::class, 'getNotifications'])->middleware('auth:api');

// Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
//     return $request->user();
// });

// Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
//     $request->user()->currentAccessToken()->delete();
//     return response()->json(['message' => 'Выход выполнен']);
// });

// Админские маршруты (только для admin, manager, bank)
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
    Route::post('users/{id}/regenerate-bank-key', [\App\Http\Controllers\Api\UserController::class, 'regenerateBankKey']);
    Route::get('activity-logs/batch', [\App\Http\Controllers\Api\ActivityLogController::class, 'batch']);

    // Статистика
    Route::get('statistics/orders', [StatisticsController::class, 'getOrderStatistics']);
    Route::get('statistics/couriers', [StatisticsController::class, 'getCourierStatistics']);
    Route::get('statistics/dashboard', [StatisticsController::class, 'getDashboardStats']);
    Route::get('statistics/bank-dashboard', [StatisticsController::class, 'getBankDashboardStats']);

    // Фотографии заказов (для админки)
    Route::get('orders/{order}/photos', [OrderPhotoController::class, 'index']);
    Route::delete('orders/{order}/photos/{photo}', [OrderPhotoController::class, 'destroy']);
    Route::get('orders/{order}/photos/download-all', [OrderPhotoController::class, 'downloadAll']);

    // Файлы заказов (для всех кроме курьеров)
    Route::get('orders/{order}/files', [OrderFileController::class, 'index']);
    Route::post('orders/{order}/files', [OrderFileController::class, 'upload']);
    Route::get('orders/{order}/files/download-all', [OrderFileController::class, 'downloadAll']);
    Route::get('orders/{order}/files/{file}', [OrderFileController::class, 'show']);
    Route::get('orders/{order}/files/{file}/download', [OrderFileController::class, 'download']);
    Route::delete('orders/{order}/files/{file}', [OrderFileController::class, 'destroy']);

    // Тестовый маршрут для проверки
    Route::get('test-download/{orderId}', function($orderId) {
        return response()->json(['message' => 'Test route works', 'orderId' => $orderId]);
    });

    // Тестовый маршрут для контроллера
    Route::get('orders/{order}/files/test', [OrderFileController::class, 'testDownload']);

        // Комментарии заказов (для админки)
        Route::get('orders/{order}/comments', [OrderCommentController::class, 'index']);
        Route::post('orders/{order}/comments', [OrderCommentController::class, 'store']);
        Route::patch('orders/{order}/comments/{comment}', [OrderCommentController::class, 'update']);
        Route::delete('orders/{order}/comments/{comment}', [OrderCommentController::class, 'destroy']);

        // Все комментарии курьера (для мобильного приложения)
        Route::get('courier/comments', [OrderCommentController::class, 'courierComments']);

    // Акт-отчеты агента (только для admin)
    // Важно: orders-for-period должен быть ПЕРЕД apiResource, иначе будет конфликт
    Route::get('agent-reports/orders-for-period', [AgentReportController::class, 'getOrdersForPeriod']);
    Route::get('agent-reports/{agent_report}/download', [AgentReportController::class, 'download']);
    Route::apiResource('agent-reports', AgentReportController::class);
});

// Мобильные маршруты для курьеров
Route::prefix('mobile')->middleware('auth:api')->group(function () {
    // Дашборд курьера
    Route::get('dashboard', [StatisticsController::class, 'getCourierDashboardStats']);

    // Только заказы курьера
    Route::get('orders', [OrderController::class, 'courierOrders']);
    Route::get('orders/{order}', [OrderController::class, 'courierOrderShow']);
    Route::patch('orders/{order}/status', [OrderController::class, 'courierUpdateStatus']);
    Route::post('orders/{order}/courier-note', [OrderController::class, 'createCourierNote']);
    Route::patch('orders/{order}/courier-note', [OrderController::class, 'updateCourierNote']);
    Route::delete('orders/{order}/courier-note', [OrderController::class, 'deleteCourierNote']);
    Route::get('order-statuses', [OrderStatusController::class, 'courierOrderStatuses']);
    Route::get('profile', [UserController::class, 'courierProfile']);
    Route::patch('profile', [UserController::class, 'courierUpdateProfile']);

    // Фотографии заказов
    Route::post('orders/{order}/photos', [OrderPhotoController::class, 'upload']);
    Route::get('orders/{order}/photos', [OrderPhotoController::class, 'index']);
    Route::delete('orders/{order}/photos/{photo}', [OrderPhotoController::class, 'destroy']);

    // Файлы заказов (только просмотр для курьеров)
    Route::get('orders/{order}/files', [OrderFileController::class, 'mobileIndex']);
    Route::get('orders/{order}/files/{file}', [OrderFileController::class, 'mobileShow']);
    Route::get('orders/{order}/files/{file}/download', [OrderFileController::class, 'mobileDownload']);
});
