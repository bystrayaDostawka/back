<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'bank_access_key' => 'nullable|string',
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Проверка активности пользователя
        if (!$user->is_active) {
            return response()->json(['message' => 'Аккаунт деактивирован'], 403);
        }

        // Запретить логин для курьера
        if ($user->role === 'courier') {
            return response()->json(['message' => 'Курьер не может войти в веб-админку'], 403);
        }

        // Проверка банковского ключа для банковских пользователей
        if ($user->role === 'bank') {
            $bankKey = $request->input('bank_access_key');

            if (!$bankKey) {
                return response()->json(['message' => 'Требуется ключ доступа для банковского пользователя'], 400);
            }

            if (!$user->isBankKeyValid($bankKey)) {
                return response()->json(['message' => 'Неверный или истекший ключ доступа'], 401);
            }
        }

        return response()->json([
            'token' => $token,
            'user'  => $user->load('bank'),
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Вы вышли из системы']);
    }

    public function mobileLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'onesignal_player_id' => 'nullable|string',
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Проверка активности пользователя
        if (!$user->is_active) {
            return response()->json(['message' => 'Аккаунт деактивирован'], 403);
        }

        // Разрешить логин только для курьеров в мобильном приложении
        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступ разрешен только для курьеров'], 403);
        }

        // Сохранение OneSignal Player ID
        if ($request->has('onesignal_player_id')) {
            $user->onesignal_player_id = $request->input('onesignal_player_id');
            $user->save();
        }

        return response()->json([
            'token' => $token,
            'user'  => $user->load('bank'),
        ]);
    }

    public function mobileLogout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Вы вышли из мобильного приложения']);
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json([
                'token' => $token,
                'user'  => Auth::user(),
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenBlacklistedException $e) {
            return response()->json(['message' => 'Токен заблокирован'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Токен истек'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Неверный токен'], 401);
        }
    }

    /**
     * Обновление OneSignal Player ID для текущего пользователя
     */
    public function updatePushToken(Request $request)
    {
        $request->validate([
            'player_id' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->onesignal_player_id = $request->input('player_id');
        $user->save();

        return response()->json([
            'message' => 'Player ID успешно обновлен',
            'player_id' => $user->onesignal_player_id,
        ]);
    }

    /**
     * Получить уведомления для текущего пользователя
     */
    public function getNotifications(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        
        // Заглушка для уведомлений
        // TODO: Реализовать логику получения уведомлений из БД
        
        return response()->json([
            'notifications' => [],
        ]);
    }
}
