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
        if ($user->hasRole('bank')) {
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
            'user'  => $user,
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
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        $user = Auth::user();

        // Проверка активности пользователя
        if (!$user->is_active) {
            return response()->json(['message' => 'Аккаунт деактивирован'], 403);
        }

        // Разрешить логин только для курьеров в мобильном приложении
        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступ разрешен только для курьеров'], 403);
        }

        return response()->json([
            'token' => $token,
            'user'  => $user,
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
}
