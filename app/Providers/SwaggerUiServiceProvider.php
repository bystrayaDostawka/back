<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SwaggerUiServiceProvider extends ServiceProvider
{
    public function boot() : void
    {
        Gate::define('viewSwaggerUI', function ($user = null) {
            // Если пользователь авторизован через JWT
            if ($user) {
                // Доступ только для администраторов (роль admin или ID = 1)
                return $user->role === 'admin' || $user->id === 1;
            }

            // Если нет авторизованного пользователя, разрешаем доступ
            // (можно изменить на false для более строгой безопасности)
            return true;
        });
    }
}
