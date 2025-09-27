<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderCommentController extends Controller
{
    /**
     * Получить все комментарии курьера
     */
    public function courierComments(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'courier') {
            return response()->json(['message' => 'Доступно только для курьеров'], 403);
        }

        // Получаем все заказы курьера с комментариями
        $orders = Order::where('courier_id', $user->id)
            ->with(['comments.user', 'bank'])
            ->get();

        $allComments = [];

        foreach ($orders as $order) {
            foreach ($order->comments as $comment) {
                $allComments[] = [
                    'id' => $comment->id,
                    'order_id' => $comment->order_id,
                    'user_id' => $comment->user_id,
                    'comment' => $comment->comment,
                    'is_completed' => $comment->is_completed,
                    'completed_at' => $comment->completed_at,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'role' => $comment->user->role,
                    ],
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'bank_name' => $order->bank->name ?? 'Не указан',
                    ],
                ];
            }
        }

        // Сортируем по дате создания (новые сверху)
        usort($allComments, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json([
            'comments' => $allComments,
            'total' => count($allComments),
            'uncompleted' => collect($allComments)->where('is_completed', false)->count(),
        ]);
    }

    /**
     * Получить комментарии заказа
     */
    public function index(Request $request, $orderId)
    {
        $order = Order::with(['comments.user'])->findOrFail($orderId);

        // Проверяем права доступа
        $user = Auth::user();
        if ($user->role === 'bank' && $order->bank_id !== $user->bank_id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }
        if ($user->role === 'courier' && $order->courier_id !== $user->id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }

        return response()->json([
            'comments' => $order->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'is_completed' => $comment->is_completed,
                    'completed_at' => $comment->completed_at?->format('Y-m-d H:i:s'),
                    'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'role' => $comment->user->role,
                    ],
                ];
            }),
        ]);
    }

    /**
     * Создать новый комментарий
     */
    public function store(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        // Проверяем права доступа
        $user = Auth::user();
        if ($user->role === 'bank' && $order->bank_id !== $user->bank_id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }
        if ($user->role === 'courier' && $order->courier_id !== $user->id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment = OrderComment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'comment' => $request->comment,
        ]);

        $comment->load('user');

        return response()->json([
            'message' => 'Комментарий добавлен',
            'comment' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'is_completed' => $comment->is_completed,
                'completed_at' => $comment->completed_at?->format('Y-m-d H:i:s'),
                'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'role' => $comment->user->role,
                ],
            ],
        ], 201);
    }

    /**
     * Обновить комментарий (отметить как выполненный/невыполненный)
     */
    public function update(Request $request, $orderId, $commentId)
    {
        $order = Order::findOrFail($orderId);
        $comment = OrderComment::findOrFail($commentId);

        // Проверяем, что комментарий принадлежит этому заказу
        if ($comment->order_id !== $order->id) {
            return response()->json(['message' => 'Комментарий не найден'], 404);
        }

        // Проверяем права доступа
        $user = Auth::user();
        if ($user->role === 'bank' && $order->bank_id !== $user->bank_id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }
        if ($user->role === 'courier' && $order->courier_id !== $user->id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }

        $request->validate([
            'is_completed' => 'required|boolean',
        ]);

        if ($request->is_completed) {
            $comment->markAsCompleted();
        } else {
            $comment->markAsIncomplete();
        }

        $comment->load('user');

        return response()->json([
            'message' => 'Комментарий обновлен',
            'comment' => [
                'id' => $comment->id,
                'comment' => $comment->comment,
                'is_completed' => $comment->is_completed,
                'completed_at' => $comment->completed_at?->format('Y-m-d H:i:s'),
                'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'role' => $comment->user->role,
                ],
            ],
        ]);
    }

    /**
     * Удалить комментарий
     */
    public function destroy(Request $request, $orderId, $commentId)
    {
        $order = Order::findOrFail($orderId);
        $comment = OrderComment::findOrFail($commentId);

        // Проверяем, что комментарий принадлежит этому заказу
        if ($comment->order_id !== $order->id) {
            return response()->json(['message' => 'Комментарий не найден'], 404);
        }

        // Проверяем права доступа
        $user = Auth::user();
        if ($user->role === 'bank' && $order->bank_id !== $user->bank_id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }
        if ($user->role === 'courier' && $order->courier_id !== $user->id) {
            return response()->json(['message' => 'Нет доступа к этому заказу'], 403);
        }

        // Только создатель комментария или админ может удалить
        if ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Нет прав для удаления этого комментария'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Комментарий удален']);
    }
}
