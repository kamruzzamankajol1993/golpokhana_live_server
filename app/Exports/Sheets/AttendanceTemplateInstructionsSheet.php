<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceTemplateInstructionsSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(private string $month)
    {
    }

    public function array(): array
    {
        return [
            ['ATTENDANCE IMPORT INSTRUCTIONS'],
            ['Template Month', $this->month],
            [],
            ['Rule', 'Instruction'],
            ['1', 'Use the Attendance Data sheet for importing attendance.'],
            ['2', 'Do not change Employee Code or Attendance Date. Employee Name is informational only.'],
            ['3', 'Allowed Status values: present, late, absent, half_day, leave, off_day. Short values P, L, A, HD, LV and O are also accepted.'],
            ['4', 'Leave Status blank when you do not want that row imported.'],
            ['5', 'Check In and Check Out must use 24-hour HH:MM format, for example 09:00 or 22:30.'],
            ['6', 'Use Shift Code from HR > Shifts. Leave blank to use the employee roster/default shift.'],
            ['7', 'Approved leave attendance is locked and cannot be overwritten by Excel import.'],
            ['8', 'Download a fresh template whenever employees, shifts or the selected month change.'],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:B1');
                $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('21352A');
                $sheet->getStyle('A1:B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5AA65');
                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(100);
                $sheet->getStyle('B1:B20')->getAlignment()->setWrapText(true);
                $sheet->freezePane('A5');
            },
        ];
    }
}
