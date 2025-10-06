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
    // Загрузка фотографий курьером (мобильное приложение)
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

        // Валидация массива файлов (поддерживаем оба формата)
        $request->validate([
            'photos' => 'sometimes|array|min:1|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:5120',
            'photos[]' => 'sometimes|array|min:1|max:10',
            'photos[].*' => 'image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $uploadedPhotos = [];
        // Пробуем получить файлы в разных форматах
        $photosFiles = $request->file('photos') ?? $request->file('photos[]');

        if (!$photosFiles) {
            return response()->json(['message' => 'Необходимо загрузить хотя бы одну фотографию'], 422);
        }

        foreach ($photosFiles as $file) {
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('order-photos', $filename, 'public');

            $photo = OrderPhoto::create([
                'order_id' => $orderId,
                'file_path' => $path,
            ]);

            $uploadedPhotos[] = [
                'id' => $photo->id,
                'url' => $photo->url,
                'created_at' => $photo->created_at,
            ];
        }


        return response()->json([
            'message' => count($uploadedPhotos) === 1 ? 'Фотография загружена' : 'Фотографии загружены',
            'photos' => $uploadedPhotos,
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

    // Удаление фотографии (курьером, админом или менеджером)
    public function destroy($orderId, $photoId)
    {
        $user = request()->user();

        $order = Order::findOrFail($orderId);

        // Проверяем права доступа
        if ($user->role === 'courier' && $order->courier_id !== $user->id) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        if (!in_array($user->role, ['admin', 'manager', 'courier'])) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        $photo = OrderPhoto::where('order_id', $orderId)->findOrFail($photoId);

        // Удаляем файл с диска
        Storage::disk('public')->delete($photo->file_path);

        // Удаляем запись из базы
        $photo->delete();

        return response()->json(['message' => 'Фотография удалена']);
    }

    // Скачивание всех фотографий заказа в архиве
    public function downloadAll($orderId)
    {
        // Проверяем, что ZipArchive доступен
        if (!class_exists('ZipArchive')) {
            return response()->json(['message' => 'ZipArchive не доступен на сервере'], 500);
        }

        $order = Order::findOrFail($orderId);
        $photos = $order->photos;

        if ($photos->isEmpty()) {
            return response()->json(['message' => 'Фотографии не найдены'], 404);
        }

        // Создаем временный ZIP архив
        $zip = new \ZipArchive();
        $zipFileName = 'temp_' . uniqid() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Создаем директорию temp если её нет
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            return response()->json(['message' => 'Не удалось создать архив'], 500);
        }

        foreach ($photos as $index => $photo) {
            if (Storage::disk('public')->exists($photo->file_path)) {
                $filePath = storage_path('app/public/' . $photo->file_path);
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                $fileName = 'photo_' . ($index + 1) . '.' . $extension;
                $zip->addFile($filePath, $fileName);
            }
        }

        $zip->close();

        // Генерируем имя файла архива
        $clientName = trim($order->surname . ' ' . $order->name . ' ' . $order->patronymic);
        $today = now()->format('Y-m-d');
        $archiveName = $clientName . '_' . $today . '_photos.zip';

        return response()->download($zipPath, $archiveName)->deleteFileAfterSend(true);
    }
}
