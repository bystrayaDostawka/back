<?php

namespace App\Exports;

use App\Models\AgentReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AgentReportExport implements FromCollection, WithTitle, WithStyles, WithColumnWidths, WithMapping, WithCustomStartCell
{
    protected $report;

    public function __construct(AgentReport $report)
    {
        $this->report = $report;
        $this->report->loadMissing([
            'banks',
            'reportOrders.order.courier',
            'reportOrders.order.bank',
        ]);
    }

    public function collection()
    {
        return $this->report->reportOrders()
            ->with(['order.courier'])
            ->get();
    }

    protected function tableHeadings(): array
    {
        return [
            '№',
            'Номер заказа',
            'Банк',
            'Продукт',
            'Клиент',
            'Адрес',
            'Дата доставки',
            'Курьер',
            'Стоимость доставки, руб.',
        ];
    }

    public function map($reportOrder): array
    {
        $order = $reportOrder->order;
        $client = $order ? trim(implode(' ', array_filter([
            $order->surname ?? '',
            $order->name ?? '',
            $order->patronymic ?? '',
        ]))) : '';

        $date = $order ? ($order->delivered_at ?? $order->delivery_at) : null;
        $dateFormatted = $date ? Carbon::parse($date)->format('d.m.Y') : '';

        return [
            '',
            $order?->order_number ?? '',
            $order?->bank?->name ?? '',
            $order?->product ?? '',
            $client,
            $order?->address ?? '',
            $dateFormatted,
            $order?->courier?->name ?? '',
            (float) ($reportOrder->delivery_cost ?? 0),
        ];
    }

    public function title(): string
    {
        return 'Акт-отчет';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 18,
            'D' => 22,
            'E' => 28,
            'F' => 35,
            'G' => 18,
            'H' => 20,
            'I' => 22,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $dataRowsCount = $this->report->reportOrders->count();
        $headerRow = 5;
        $firstDataRow = 6;
        $lastDataRow = $firstDataRow + $dataRowsCount - 1;
        $totalRow = $lastDataRow + 1;
        $summaryRow = $totalRow + 2;

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'АКТ-ОТЧЕТ АГЕНТА');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->setCellValue('A2', 'Период: с ' . $this->report->period_from->format('d.m.Y') . ' по ' . $this->report->period_to->format('d.m.Y'));
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getFont()->setSize(12);

        $sheet->fromArray([$this->tableHeadings()], null, 'A' . $headerRow);
        $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        if ($dataRowsCount > 0) {
            $dataRange = 'A' . $firstDataRow . ':I' . $lastDataRow;
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        $row = $firstDataRow;
        $num = 1;
        foreach ($this->report->reportOrders as $reportOrder) {
            $sheet->setCellValue('A' . $row, $num);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            $num++;
        }

        $sheet->mergeCells('A' . $totalRow . ':H' . $totalRow);
        $sheet->setCellValue('A' . $totalRow, 'ИТОГО:');
        if ($dataRowsCount > 0) {
            $sheet->setCellValue('I' . $totalRow, '=SUM(I' . $firstDataRow . ':I' . $lastDataRow . ')');
        } else {
            $sheet->setCellValue('I' . $totalRow, '0');
        }
        
        $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->applyFromArray([
            'font' => ['bold' => true],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->mergeCells('A' . $summaryRow . ':I' . ($summaryRow + 1));
        if ($dataRowsCount > 0) {
            $sheet->setCellValue('A' . $summaryRow, '="Итого стоимость доставки: " & I' . $totalRow . ' & " руб."');
        } else {
            $sheet->setCellValue('A' . $summaryRow, 'Итого стоимость доставки: 0,00 руб.');
        }
        $sheet->getStyle('A' . $summaryRow . ':I' . ($summaryRow + 1))->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'E0EDFF'],
            ],
        ]);

        $sheet->getStyle('A' . $firstDataRow . ':A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G' . $firstDataRow . ':G' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I' . $firstDataRow . ':I' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I' . $firstDataRow . ':I' . $lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00');

        if ($dataRowsCount > 0) {
            $sheet->getStyle('I' . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $sheet->getStyle('D' . $firstDataRow . ':F' . $lastDataRow)->getAlignment()->setWrapText(true);

        return [];
    }

    public function startCell(): string
    {
        return 'A6';
    }
}

