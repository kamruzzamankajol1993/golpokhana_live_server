<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomersExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Customer::select('id', 'name', 'primary_id', 'phone', 'email', 'points')->get();
    }

    public function headings(): array
    {
        return ["ID", "Name", "Primary ID", "Phone", "Email", "Points"];
    }
}
