<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

final class AccountingFinancialReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private array $rows, private string $reportType) {}

    public function array(): array { return $this->rows; }
    public function title(): string { return mb_substr(ucwords(str_replace('-', ' ', $this->reportType)), 0, 31); }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A5');
        $last = $sheet->getHighestColumn();
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:'.$last.'1')->getFont()->getColor()->setRGB('21352A');
        $sheet->getStyle('A2:'.$last.'3')->getFont()->getColor()->setRGB('687D72');
        $sheet->getDefaultRowDimension()->setRowHeight(21);
        foreach ($this->rows as $idx => $row) {
            $rowNum = $idx + 1;
            $first = strtoupper((string)($row[0] ?? ''));
            if ($rowNum < 5) continue;
            $isSection = in_array($first, ['ASSET','LIABILITY','EQUITY','INCOME','CONTRA REVENUE','COGS','EXPENSE','AR','AP','CODE','DATE','TAX TYPE'], true);
            $isTotal = str_contains($first,'TOTAL') || str_contains($first,'ENDING ') || str_contains($first,'DIFFERENCE') || str_contains($first,'PROFIT');
            if ($isSection || $isTotal) {
                $range = 'A'.$rowNum.':'.$last.$rowNum;
                $sheet->getStyle($range)->getFont()->setBold(true);
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($isSection ? 'EAF2EB' : 'F4F6F3');
            }
        }
        return [];
    }
}
