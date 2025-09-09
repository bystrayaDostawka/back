<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Обработка ошибок для API запросов
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle API exceptions
     */
    private function handleApiException($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Не авторизован'
            ], 401);
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $exception->errors()
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Ресурс не найден'
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'message' => 'Маршрут не найден'
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'message' => 'Метод не разрешен'
            ], 405);
        }

        // Для остальных ошибок
        $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
        
        return response()->json([
            'message' => $statusCode === 500 ? 'Внутренняя ошибка сервера' : $exception->getMessage()
        ], $statusCode);
    }
}
