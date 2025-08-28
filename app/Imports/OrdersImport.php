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
        $formats = [
            'd.m.Y H:i',
            'd.m.Y H:i:s',
            'd.m.Y',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d',
        ];
        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, trim($value));
                if ($dt !== false) {
                    if (strpos($format, 'H:i') === false) {
                        $dt->setTime(0, 0, 0);
                    }
                    return $dt->format('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                // continue
            }
        }
        return null;
    }

    public function rules(): array
    {
        Log::info('Excel rules input:', request()->all());
        return [
            '*.product'     => 'required|string|max:255',
            '*.name'        => 'required|string|max:255',
            '*.surname'     => 'required|string|max:255',
            '*.patronymic'  => 'required|string|max:255',
            '*.phone'       => ['required', 'regex:/^\+?[0-9]{10,15}$/'],
            '*.address'     => 'required|string|max:255',
            '*.delivery_at' => 'required',
        ];
    }
}
