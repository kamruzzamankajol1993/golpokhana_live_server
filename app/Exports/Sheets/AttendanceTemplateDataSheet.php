<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceTemplateDataSheet implements FromArray, WithHeadings, WithEvents, WithTitle
{
    public function __construct(private array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Employee Code',
            'Employee Name',
            'Attendance Date',
            'Shift Code',
            'Status',
            'Check In',
            'Check Out',
            'Notes',
        ];
    }

    public function title(): string
    {
        return 'Attendance Data';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(2, count($this->rows) + 1);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:H{$lastRow}");
                $sheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('21352A');
                $sheet->getStyle("A1:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DFD0BC');
                $sheet->getStyle("A1:H{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $widths = ['A' => 18, 'B' => 28, 'C' => 18, 'D' => 18, 'E' => 16, 'F' => 14, 'G' => 14, 'H' => 34];
                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Invalid attendance status');
                $validation->setError('Choose a status from the dropdown list.');
                $validation->setPromptTitle('Attendance Status');
                $validation->setPrompt('Blank rows are ignored during import.');
                $validation->setFormula1('"present,late,absent,half_day,leave,off_day"');

                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getCell("E{$row}")->setDataValidation(clone $validation);
                }

                $sheet->getStyle("C2:G{$lastRow}")->getNumberFormat()->setFormatCode('@');
            },
        ];
    }
}
