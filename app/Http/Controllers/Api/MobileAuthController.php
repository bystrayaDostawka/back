<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWT;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        $user = Auth::user();

        // Разрешить логин только для курьеров в мобильном приложении
        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступ разрешен только для курьеров'], 403);
        }

        // Используем стандартный токен с увеличенным временем жизни (7 дней)
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    public function logout()
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

    public function me()
    {
        return response()->json(Auth::user());
    }
}
