<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderPhotoController extends Controller
{
    // Загрузка фотографии курьером (мобильное приложение)
    public function upload(Request $request, $orderId)
    {
        $user = $request->user();

        // Проверяем, что пользователь - курьер
        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $order = Order::findOrFail($orderId);

        // Проверяем, что заказ принадлежит этому курьеру
        if ($order->courier_id !== $user->id) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        $file = $request->file('photo');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('order-photos', $filename, 'public');

        $photo = OrderPhoto::create([
            'order_id' => $orderId,
            'file_path' => $path,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
            'created_at' => $photo->created_at,
        ], 201);
    }

    // Получение фотографий заказа (для админки и мобильного приложения)
    public function index($orderId)
    {
        $order = Order::findOrFail($orderId);

        $photos = $order->photos()->orderBy('created_at', 'desc')->get();

        return response()->json($photos->map(function ($photo) {
            return [
                'id' => $photo->id,
                'url' => $photo->url,
                'created_at' => $photo->created_at,
            ];
        }));
    }

    // Удаление фотографии (только курьером)
    public function destroy($orderId, $photoId)
    {
        $user = request()->user();

        // Проверяем, что пользователь - курьер
        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $order = Order::findOrFail($orderId);

        // Проверяем, что заказ принадлежит этому курьеру
        if ($order->courier_id !== $user->id) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        $photo = OrderPhoto::where('order_id', $orderId)->findOrFail($photoId);

        // Удаляем файл с диска
        Storage::disk('public')->delete($photo->file_path);

        // Удаляем запись из базы
        $photo->delete();

        return response()->json(['message' => 'Фотография удалена']);
    }
}
