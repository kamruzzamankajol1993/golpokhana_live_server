<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceRowsImport implements WithMultipleSheets
{
    private AttendanceDataSheetImport $attendanceSheet;

    public function __construct()
    {
        $this->attendanceSheet = new AttendanceDataSheetImport();
    }

    public function sheets(): array
    {
        // Only the first "Attendance Data" sheet is imported.
        // The template's Instructions sheet is intentionally ignored.
        return [
            0 => $this->attendanceSheet,
        ];
    }

    public function rows(): Collection
    {
        return $this->attendanceSheet->rows;
    }
}
