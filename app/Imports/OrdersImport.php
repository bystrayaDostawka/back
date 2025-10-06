<?php

namespace App\Imports;

use App\Models\Order;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrdersImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $bankId;

    public function __construct($bankId)
    {
        $this->bankId = $bankId;
    }

    public function model(array $row)
    {
        Log::info('Excel row:', $row);
        $deliveryAt = $this->parseDate($row['delivery_at']);

        Log::info('Order create array:', [
            'bank_id'      => $this->bankId,
            'product'      => $row['product'],
            'name'         => $row['name'],
            'surname'      => $row['surname'],
            'patronymic'   => $row['patronymic'],
            'phone'        => $row['phone'],
            'address'      => $row['address'],
            'delivery_at'  => $deliveryAt,
        ]);

        return new Order([
            'bank_id'      => $this->bankId,
            'product'      => $row['product'],
            'name'         => $row['name'],
            'surname'      => $row['surname'],
            'patronymic'   => $row['patronymic'],
            'phone'        => $row['phone'],
            'address'      => $row['address'],
            'delivery_at'  => $deliveryAt,
            'order_status_id' => 1, // статус "Новая" по умолчанию
        ]);
    }

    protected function parseDate($value)
    {
        // Если значение пустое, возвращаем текущую дату
        if (empty($value) || is_null($value)) {
            Log::warning('Empty delivery_at value, using current date');
            return now()->format('Y-m-d H:i:s');
        }

        // Преобразуем значение в строку и очищаем
        $value = trim((string)$value);
        
        // Если это число (Excel serial date)
        if (is_numeric($value)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $dt->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                Log::warning('Failed to parse Excel serial date: ' . $value, ['error' => $e->getMessage()]);
            }
        }

        $formats = [
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd.m.Y',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y',
        ];
        
        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt !== false) {
                    // Если формат не содержит время, устанавливаем время на 00:00:00
                    if (strpos($format, 'H:i') === false) {
                        $dt->setTime(0, 0, 0);
                    }
                    return $dt->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                // continue
            }
        }
        
        // Попытка использовать Carbon::parse как последний шанс
        try {
            $dt = Carbon::parse($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning('Failed to parse date: ' . $value, ['error' => $e->getMessage()]);
        }
        
        // Если ничего не помогло, используем текущую дату
        Log::warning('Could not parse delivery_at date: ' . $value . ', using current date');
        return now()->format('Y-m-d H:i:s');
    }

    public function rules(): array
    {
        Log::info('Excel rules input:', request()->all());
        return [
            '*.product'     => 'required|string|max:255',
            '*.name'        => 'required|string|max:255',
            '*.surname'     => 'required|string|max:255',
            '*.patronymic'  => 'required|string|max:255',
            '*.phone'       => ['required', 'regex:/^\+?[0-9\s\-\(\)]{10,20}$/'],
            '*.address'     => 'required|string|max:255',
            '*.delivery_at' => 'required',
        ];
    }

    /**
     * Кастомные сообщения об ошибках валидации
     */
    public function customValidationMessages()
    {
        return [
            '*.product.required'     => 'Поле "Продукт" обязательно для заполнения.',
            '*.name.required'        => 'Поле "Имя" обязательно для заполнения.',
            '*.surname.required'     => 'Поле "Фамилия" обязательно для заполнения.',
            '*.patronymic.required'  => 'Поле "Отчество" обязательно для заполнения.',
            '*.phone.required'       => 'Поле "Телефон" обязательно для заполнения.',
            '*.phone.regex'          => 'Поле "Телефон" имеет неверный формат.',
            '*.address.required'     => 'Поле "Адрес" обязательно для заполнения.',
            '*.delivery_at.required' => 'Поле "Дата доставки" обязательно для заполнения.',
        ];
    }
}
