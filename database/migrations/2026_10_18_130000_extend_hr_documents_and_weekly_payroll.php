<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'cv_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('cv_path', 500)->nullable()->after('nid_image');
            });
        }

        if (Schema::hasTable('employee_educations') && !Schema::hasColumn('employee_educations', 'certificate_file_path')) {
            Schema::table('employee_educations', function (Blueprint $table) {
                $table->string('certificate_file_path', 500)->nullable()->after('result');
            });
        }

        if (Schema::hasTable('employee_certificates') && !Schema::hasColumn('employee_certificates', 'duration')) {
            Schema::table('employee_certificates', function (Blueprint $table) {
                $table->string('duration', 180)->nullable()->after('title');
            });
        }

        if (Schema::hasTable('employee_salary_structures') && !Schema::hasColumn('employee_salary_structures', 'pay_frequency')) {
            Schema::table('employee_salary_structures', function (Blueprint $table) {
                $table->string('pay_frequency', 20)->default('monthly')->after('basic_salary');
            });
        }

        if (!Schema::hasTable('employee_emergency_contacts')) {
            Schema::create('employee_emergency_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('name', 180);
                $table->string('phone', 40);
                $table->timestamps();
                $table->index('employee_id');
            });

            // Preserve contacts already saved in the old single-contact fields.
            if (Schema::hasTable('employees')) {
                $legacyContacts = DB::table('employees')
                    ->where(function ($query) {
                        $query->whereNotNull('emergency_contact_name')
                            ->orWhereNotNull('emergency_contact_phone');
                    })
                    ->get(['id', 'emergency_contact_name', 'emergency_contact_phone', 'created_at', 'updated_at']);

                foreach ($legacyContacts as $contact) {
                    $name = trim((string) ($contact->emergency_contact_name ?? ''));
                    $phone = trim((string) ($contact->emergency_contact_phone ?? ''));
                    if ($name === '' || $phone === '') {
                        continue;
                    }

                    DB::table('employee_emergency_contacts')->insert([
                        'employee_id' => $contact->id,
                        'name' => $name,
                        'phone' => $phone,
                        'created_at' => $contact->created_at ?? now(),
                        'updated_at' => $contact->updated_at ?? now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('payroll_runs') && !Schema::hasColumn('payroll_runs', 'period_type')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->string('period_type', 20)->default('monthly')->after('payroll_month');
            });
        }

        // A month can now contain several weekly runs as well as its monthly run.
        // Replace the old branch/month uniqueness with exact-period uniqueness.
        if (Schema::hasTable('payroll_runs') && Schema::hasColumn('payroll_runs', 'branch_id')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropUnique('payroll_runs_branch_month_unique');
                $table->unique(
                    ['branch_id', 'period_type', 'period_start', 'period_end'],
                    'payroll_runs_branch_period_unique'
                );
            });
        } elseif (Schema::hasTable('payroll_runs')) {
            Schema::table('payroll_runs', function (Blueprint $table) {
                $table->dropUnique(['payroll_month']);
                $table->unique(['period_type', 'period_start', 'period_end'], 'payroll_runs_period_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_runs')) {
            if (Schema::hasColumn('payroll_runs', 'branch_id')) {
                Schema::table('payroll_runs', function (Blueprint $table) {
                    $table->dropUnique('payroll_runs_branch_period_unique');
                });
                // Do not restore branch/month uniqueness here: weekly payrolls can
                // legitimately create multiple runs for a month after this migration.
            } else {
                Schema::table('payroll_runs', function (Blueprint $table) {
                    $table->dropUnique('payroll_runs_period_unique');
                });
            }
            if (Schema::hasColumn('payroll_runs', 'period_type')) {
                Schema::table('payroll_runs', function (Blueprint $table) {
                    $table->dropColumn('period_type');
                });
            }
        }

        Schema::dropIfExists('employee_emergency_contacts');

        if (Schema::hasTable('employee_salary_structures') && Schema::hasColumn('employee_salary_structures', 'pay_frequency')) {
            Schema::table('employee_salary_structures', function (Blueprint $table) {
                $table->dropColumn('pay_frequency');
            });
        }
        if (Schema::hasTable('employee_certificates') && Schema::hasColumn('employee_certificates', 'duration')) {
            Schema::table('employee_certificates', function (Blueprint $table) {
                $table->dropColumn('duration');
            });
        }
        if (Schema::hasTable('employee_educations') && Schema::hasColumn('employee_educations', 'certificate_file_path')) {
            Schema::table('employee_educations', function (Blueprint $table) {
                $table->dropColumn('certificate_file_path');
            });
        }
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'cv_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('cv_path');
            });
        }
    }
};
