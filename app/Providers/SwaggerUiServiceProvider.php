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
            if ($user && $user->email) {
                return in_array($user->email, [
                    'admin@test.ru',
                    'manager@test.ru',
                ]);
            }

            // Если нет авторизованного пользователя, разрешаем доступ
            // (можно изменить на false для более строгой безопасности)
            return true;
        });
    }
}
