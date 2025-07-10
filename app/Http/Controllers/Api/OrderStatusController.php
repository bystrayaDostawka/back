<?php

namespace App\Http\Controllers\Api;

use App\Models\OrderStatus;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class OrderStatusController extends Controller
{
    public function index()
    {
        return OrderStatus::orderBy('id', 'asc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'color' => 'nullable|string|max:32'
        ]);
        $status = OrderStatus::create($data);
        return response()->json($status, 201);
    }

    public function show($id)
    {
        $status = OrderStatus::findOrFail($id);
        return response()->json($status);
    }

    public function update(Request $request, $id)
    {
        $status = OrderStatus::findOrFail($id);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'color' => 'nullable|string|max:32'
        ]);
        $status->update($data);
        return response()->json($status);
    }

    public function destroy($id)
    {
        if (in_array($id, [1, 2, 3, 4, 5])) {
            return response()->json(['message' => 'Этот статус удалять нельзя'], 403);
        }
        $status = OrderStatus::findOrFail($id);
        $status->delete();
        return response()->json(null, 204);
    }
}
