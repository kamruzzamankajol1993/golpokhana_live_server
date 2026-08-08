<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;

class TestExport implements FromCollection
{
    public function collection()
    {
        return new Collection([
            ['ID', 'Name', 'Email'],
            ['1', 'John Doe', 'john@example.com'],
            ['2', 'Jane Smith', 'jane@example.com'],
        ]);
    }
}
