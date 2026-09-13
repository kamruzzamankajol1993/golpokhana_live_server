<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrSimplifiedPayrollSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasColumn('salary_components', 'component_group')) {
            return;
        }

        $branches = Schema::hasColumn('salary_components', 'branch_id')
            ? DB::table('branches')->pluck('id')->all()
            : [null];
        if (empty($branches)) $branches = [null];

        $components = [
            ['name'=>'Basic','payslip_label'=>'Basic','code'=>'BASIC','component_group'=>'salary','type'=>'earning','calculation_type'=>'fixed','rule_code'=>'basic','is_required'=>true,'is_system'=>true,'sort_order'=>10,'allow_employee_override'=>false],
            ['name'=>'House Rent','code'=>'HOUSE_RENT','component_group'=>'salary','type'=>'earning','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>50,'rule_code'=>'standard','sort_order'=>20],
            ['name'=>'Medical','code'=>'MEDICAL','component_group'=>'salary','type'=>'earning','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>10,'rule_code'=>'standard','sort_order'=>30],
            ['name'=>'Conveyance','code'=>'CONVEYANCE','component_group'=>'salary','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>40],

            ['name'=>'OT Amount (Day Off)','code'=>'OT_DAY_OFF','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'ot_day_off','sort_order'=>110],
            ['name'=>'OT Amount (GOV Off)','code'=>'OT_GOV_OFF','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'ot_gov_off','sort_order'=>120],
            ['name'=>'Breakfast','code'=>'BREAKFAST','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>130],
            ['name'=>'Other','code'=>'OTHER_ALLOWANCE','component_group'=>'allowance','type'=>'earning','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>140],
            ['name'=>'Arrear','code'=>'ARREAR','component_group'=>'allowance','type'=>'earning','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>150],
            ['name'=>'Adjustment (Last Month) Addition','code'=>'LAST_MONTH_ADDITION','component_group'=>'allowance','type'=>'earning','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>160],
            ['name'=>'Lunch','code'=>'LUNCH','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>170],

            ['name'=>'Late Deduction Amount','code'=>'LATE','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>100,'rule_code'=>'late','sort_order'=>210],
            ['name'=>'LWP + Absent Amount','code'=>'LWP_ABSENT','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>100,'rule_code'=>'lwp_absent','sort_order'=>220],
            ['name'=>'Fine','code'=>'FINE','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>230],
            ['name'=>'Salary Advance','code'=>'SALARY_ADVANCE','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'salary_advance','sort_order'=>240,'allow_employee_override'=>false],
            ['name'=>'Loan Adjustment','code'=>'LOAN_ADJUSTMENT','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'loan_adjustment','sort_order'=>250,'allow_employee_override'=>false],
            ['name'=>'Sales Adjustment','code'=>'SALES_ADJUSTMENT','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>260],
            ['name'=>'TDS','code'=>'TDS','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>270],
            ['name'=>'GYM','code'=>'GYM','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>280],
            ['name'=>'Other Deduction','payslip_label'=>'Other','code'=>'OTHER_DEDUCTION','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>290],
            ['name'=>'Bed Facility','code'=>'BED_FACILITY','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>300],
            ['name'=>'Adjustment (Last Month) Deduction','code'=>'LAST_MONTH_DEDUCTION','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>310],
        ];

        foreach ($branches as $branchId) {
            foreach ($components as $component) {
                $values = array_merge([
                    'payslip_label'=>null,'percentage_of'=>null,'default_amount'=>0,'default_percentage'=>0,
                    'global_configured'=>true,'apply_to_all'=>true,'allow_employee_override'=>true,
                    'show_zero_on_payslip'=>true,'is_taxable'=>false,'is_required'=>false,'is_system'=>false,
                    'description'=>'Configurable payroll component. Employee override takes priority over this global rule.',
                    'status'=>true,
                ], $component);
                if (Schema::hasColumn('salary_components','branch_id')) $values['branch_id']=$branchId;
                $query=SalaryComponent::query()->where('code',$component['code']);
                if (Schema::hasColumn('salary_components','branch_id')) $query->where('branch_id',$branchId);
                $model=$query->first();
                $model ? $model->fill($values)->save() : SalaryComponent::create($values);
            }

            $legacy=['OT','ABSENT','UNPAID','HALF-DAY'];
            $q=SalaryComponent::whereIn('code',$legacy);
            if (Schema::hasColumn('salary_components','branch_id')) $q->where('branch_id',$branchId);
            $q->update(['status'=>false]);
        }
    }
}
