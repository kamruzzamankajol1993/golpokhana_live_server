<?php

namespace App\Exports;

use App\Exports\Sheets\AttendanceTemplateDataSheet;
use App\Exports\Sheets\AttendanceTemplateInstructionsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceTemplateExport implements WithMultipleSheets
{
    public function __construct(
        private array $rows,
        private string $month
    ) {
    }

    public function sheets(): array
    {
        return [
            new AttendanceTemplateDataSheet($this->rows),
            new AttendanceTemplateInstructionsSheet($this->month),
        ];
    }
}
