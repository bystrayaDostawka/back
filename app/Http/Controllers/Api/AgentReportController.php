<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\AgentReportsRepository;
use App\Exports\AgentReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AgentReportController extends Controller
{
    protected AgentReportsRepository $repository;

    public function __construct(AgentReportsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', \App\Models\AgentReport::class);

        $filters = [
            'bank_id' => $request->query('bank_id'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'period_from' => $request->query('period_from'),
            'period_to' => $request->query('period_to'),
        ];

        $reports = $this->repository->getItems($request->user(), $filters);
        return response()->json($reports);
    }

    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\AgentReport::class);

        $data = $request->validate([
            'bank_ids' => 'required|array|min:1',
            'bank_ids.*' => 'exists:banks,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'delivery_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'orders' => 'required|array|min:1',
            'orders.*.order_id' => 'required|exists:orders,id',
            'orders.*.delivery_cost' => 'required|numeric|min:0',
        ]);

        $report = $this->repository->createItem($data, $request->user()->id);

        // Генерируем Excel файл
        try {
            $this->generateExcelFile($report);
        } catch (\Exception $e) {
            \Log::error('Ошибка при генерации Excel файла для акта-отчета', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
            // Не прерываем создание акта, файл можно сгенерировать позже
        }

        return response()->json($report->load(['banks', 'creator', 'reportOrders.order']), 201);
    }

    public function show($agent_report)
    {
        $report = $this->repository->findItem($agent_report);
        $this->authorize('view', $report);
        // Загружаем связи для фронтенда
        $report->load(['banks', 'creator', 'reportOrders.order.bank', 'reportOrders.order.courier', 'reportOrders.order.status']);
        return response()->json($report);
    }

    public function update(Request $request, $agent_report)
    {
        $report = $this->repository->findItem($agent_report);
        $this->authorize('update', $report);

        $data = $request->validate([
            'bank_ids' => 'nullable|array|min:1',
            'bank_ids.*' => 'exists:banks,id',
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date|after_or_equal:period_from',
            'status' => 'nullable|in:formed,under_review,approved,rejected',
            'delivery_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'orders' => 'nullable|array',
            'orders.*.order_id' => 'required_with:orders|exists:orders,id',
            'orders.*.delivery_cost' => 'required_with:orders|numeric|min:0',
        ]);

        // Сохраняем путь к старому файлу для удаления
        $oldFilePath = $report->excel_file_path;

        $shouldRegenerateExcel = isset($data['delivery_cost'])
            || isset($data['orders'])
            || isset($data['bank_ids'])
            || isset($data['period_from'])
            || isset($data['period_to']);

        $report = $this->repository->updateItem($agent_report, $data);

        // Если изменились ключевые данные, перегенерируем Excel
        if ($shouldRegenerateExcel) {
            // Удаляем старый файл если существует
            if ($oldFilePath && Storage::exists($oldFilePath)) {
                Storage::delete($oldFilePath);
            }
            $this->generateExcelFile($report->fresh());
        }

        return response()->json($report);
    }

    public function destroy($agent_report)
    {
        $report = $this->repository->findItem($agent_report);
        $this->authorize('delete', $report);

        // Удаляем Excel файл если существует
        if ($report->excel_file_path && Storage::exists($report->excel_file_path)) {
            Storage::delete($report->excel_file_path);
        }

        $this->repository->deleteItem($agent_report);
        return response()->json(null, 204);
    }

    /**
     * Скачать Excel файл акта-отчета
     */
    public function download($agent_report)
    {
        $report = $this->repository->findItem($agent_report);
        $this->authorize('view', $report);

        // Всегда генерируем актуальный файл
        if ($report->excel_file_path && Storage::exists($report->excel_file_path)) {
            Storage::delete($report->excel_file_path);
        }
        $this->generateExcelFile($report->fresh());
        $report->refresh();

        $report->load('banks');
        $filePath = storage_path('app/' . $report->excel_file_path);
        $banksNames = $report->banks->pluck('name')->implode(', ');
        $fileName = 'Акт-отчет_' . ($banksNames ?: 'банки') . '_' . $report->period_from->format('Y-m-d') . '_' . $report->period_to->format('Y-m-d') . '.xlsx';

        return response()->download($filePath, $fileName);
    }

    /**
     * Получить заказы за период для предзаполнения формы
     */
    public function getOrdersForPeriod(Request $request)
    {
        $this->authorize('create', \App\Models\AgentReport::class);

        $data = $request->validate([
            'bank_ids' => 'required|array|min:1',
            'bank_ids.*' => 'exists:banks,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
        ]);

        $orders = $this->repository->getOrdersForPeriod(
            $data['bank_ids'],
            $data['period_from'],
            $data['period_to']
        );

        return response()->json($orders);
    }

    /**
     * Генерировать Excel файл для акта-отчета
     */
    protected function generateExcelFile($report)
    {
        // Создаем директорию если не существует
        $directory = 'agent-reports';
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        // Генерируем файл
        $fileName = 'agent_report_' . $report->id . '_' . time() . '.xlsx';
        $filePath = $directory . '/' . $fileName;

        Excel::store(new AgentReportExport($report), $filePath);

        // Обновляем путь к файлу в базе
        $report->excel_file_path = $filePath;
        $report->save();

        return $filePath;
    }
}

