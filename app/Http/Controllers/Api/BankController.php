<?php

namespace App\Http\Controllers\Api;

use App\Models\Bank;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BankController extends Controller
{
    public function index()
    {
        return Bank::orderBy('id', 'asc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        $bank = Bank::create($data);
        return response()->json($bank, 201);
    }

    public function show($id)
    {
        return Bank::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $bank = Bank::findOrFail($id);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        $bank->update($data);
        return response()->json($bank);
    }

    public function destroy($id)
    {
        $bank = Bank::findOrFail($id);
        $bank->delete();
        return response()->json(null, 204);
    }
}
