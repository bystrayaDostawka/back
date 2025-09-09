<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\BanksRepository;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Carbon;

class BankController extends Controller
{
    protected BanksRepository $banksRepository;

    public function __construct(BanksRepository $banksRepository)
    {
        $this->banksRepository = $banksRepository;
    }

    public function index()
    {
        return response()->json($this->banksRepository->getItems());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'order_prefix' => 'nullable|string|max:10',
        ]);

        $bank = $this->banksRepository->createItem($data);
        return response()->json($bank, 201);
    }

    public function show($id)
    {
        return response()->json($this->banksRepository->findItem($id));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'order_prefix' => 'nullable|string|max:10',
        ]);

        $bank = $this->banksRepository->updateItem($id, $data);
        return response()->json($bank);
    }

    public function destroy($id)
    {
        $ok = $this->banksRepository->deleteItem($id);
        return response()->json(null, $ok ? 204 : 400);
    }

    public function activityLog($id)
    {
        $user = request()->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Нет доступа к логам'], 403);
        }
        $logs = Activity::where('log_name', 'bank')
            ->where('subject_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $result = $logs->map(function ($log) {
            $changes = [];
            $fields = array_unique(array_merge(
                array_keys($log->properties['attributes'] ?? []),
                array_keys($log->properties['old'] ?? [])
            ));
            foreach ($fields as $key) {
                $old = $log->properties['old'][$key] ?? null;
                $new = $log->properties['attributes'][$key] ?? null;
                $label = match ($key) {
                    'name' => 'Название',
                    'phone' => 'Телефон',
                    'email' => 'Email',
                    'order_prefix' => 'Префикс заказов',
                    default => $key,
                };
                $changes[$label] = ['old' => $old, 'new' => $new];
            }
            return [
                'description' => $log->description,
                'changes'     => $changes,
                'user'        => $log->causer ? $log->causer->name : null,
                'date'        => Carbon::parse($log->created_at)->format('d.m.Y H:i'),
            ];
        });
        return response()->json($result);
    }
}
