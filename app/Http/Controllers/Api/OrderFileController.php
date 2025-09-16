<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderFileController extends Controller
{
    // Загрузка файла к заказу
    public function upload(Request $request, $orderId)
    {
        $user = $request->user();

        // Проверяем, что пользователь НЕ курьер
        if ($user->role === 'courier') {
            return response()->json(['message' => 'Курьеры не могут загружать файлы'], 403);
        }

        $order = Order::findOrFail($orderId);

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // Определяем тип файла
        $fileType = $this->getFileType($mimeType);

        // Генерируем уникальное имя файла
        $filename = Str::uuid() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('order-files', $filename, 'public');

        $orderFile = OrderFile::create([
            'order_id' => $orderId,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_type' => $fileType,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'uploaded_by' => $user->id,
        ]);

        return response()->json([
            'id' => $orderFile->id,
            'file_name' => $orderFile->file_name,
            'file_type' => $orderFile->file_type,
            'file_size' => $orderFile->file_size,
            'formatted_size' => $orderFile->formatted_size,
            'url' => $orderFile->url,
            'uploaded_by' => [
                'id' => $orderFile->uploader->id,
                'name' => $orderFile->uploader->name,
            ],
            'created_at' => $orderFile->created_at,
        ], 201);
    }

    // Получение файлов заказа
    public function index($orderId)
    {
        $order = Order::findOrFail($orderId);

        $files = $order->files()->with('uploader:id,name')->orderBy('created_at', 'desc')->get();

        return response()->json($files->map(function ($file) {
            return [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_type' => $file->file_type,
                'file_size' => $file->file_size,
                'formatted_size' => $file->formatted_size,
                'url' => $file->url,
                'uploaded_by' => [
                    'id' => $file->uploader->id,
                    'name' => $file->uploader->name,
                ],
                'created_at' => $file->created_at,
            ];
        }));
    }

    // Скачивание файла
    public function download($orderId, $fileId)
    {
        $order = Order::findOrFail($orderId);
        $file = $order->files()->findOrFail($fileId);

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['message' => 'Файл не найден'], 404);
        }

        $filePath = storage_path('app/public/' . $file->file_path);
        return response()->download($filePath, $file->file_name);
    }

    // Удаление файла
    public function destroy($orderId, $fileId)
    {
        $user = request()->user();

        // Проверяем, что пользователь НЕ курьер
        if ($user->role === 'courier') {
            return response()->json(['message' => 'Курьеры не могут удалять файлы'], 403);
        }

        $order = Order::findOrFail($orderId);
        $file = $order->files()->findOrFail($fileId);

        // Проверяем, что пользователь может удалить файл (только тот, кто загрузил, или админ)
        if ($file->uploaded_by !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Недостаточно прав для удаления файла'], 403);
        }

        // Удаляем файл с диска
        Storage::disk('public')->delete($file->file_path);

        // Удаляем запись из базы
        $file->delete();

        return response()->json(['message' => 'Файл удалён']);
    }

    // Получение информации о файле
    public function show($orderId, $fileId)
    {
        $order = Order::findOrFail($orderId);
        $file = $order->files()->with('uploader:id,name')->findOrFail($fileId);

        return response()->json([
            'id' => $file->id,
            'file_name' => $file->file_name,
            'file_type' => $file->file_type,
            'file_size' => $file->file_size,
            'formatted_size' => $file->formatted_size,
            'url' => $file->url,
            'uploaded_by' => [
                'id' => $file->uploader->id,
                'name' => $file->uploader->name,
            ],
            'created_at' => $file->created_at,
            'updated_at' => $file->updated_at,
        ]);
    }

    // Определение типа файла по MIME типу
    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'application/pdf')) {
            return 'pdf';
        }

        if (in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/rtf'
        ])) {
            return 'document';
        }

        if (in_array($mimeType, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'
        ])) {
            return 'spreadsheet';
        }

        if (in_array($mimeType, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ])) {
            return 'presentation';
        }

        if (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed'
        ])) {
            return 'archive';
        }

        return 'document';
    }
}
