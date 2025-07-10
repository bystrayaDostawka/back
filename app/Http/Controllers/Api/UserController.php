<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool)$request->input('is_active'));
        }

        $query->with('bank');

        return $query->orderBy('id', 'desc')->get();
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'phone'    => 'nullable|string|max:255',
            'role'     => ['required', Rule::in(['admin', 'manager', 'courier', 'bank'])],
            'bank_id'  => 'nullable|exists:banks,id',
            'is_active' => 'boolean',
            'note'     => 'nullable|string',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        return response()->json($user, 201);
    }

    public function show($id)
    {
        return User::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $id,
            'phone'    => 'nullable|string|max:255',
            'role'     => ['required', Rule::in(['admin', 'manager', 'courier', 'bank'])],
            'bank_id'  => 'nullable|exists:banks,id',
            'is_active' => 'boolean',
            'note'     => 'nullable|string',
            'password' => 'nullable|string|min:6',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['message' => 'Нельзя удалить последнего администратора'], 400);
        }

        $user->delete();
        return response()->json(null, 204);
    }
}
