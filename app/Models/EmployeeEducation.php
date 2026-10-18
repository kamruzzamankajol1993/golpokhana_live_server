<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    /**
     * Match the existing database table name explicitly.
     * This avoids singular-table lookups such as `employee_education`.
     */
    protected $table = 'employee_educations';

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
