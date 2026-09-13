<?php

namespace App\Services\Hr;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryStructure;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

class PayrollCalculator
{
    private PayrollSetting $payrollSetting;
    private AttendanceSetting $attendanceSetting;
    private Collection $salaryComponents;

    public function __construct(private PayrollRecoveryService $recoveryService)
    {
        $this->payrollSetting = PayrollSetting::firstOrCreate([], [
            'salary_cycle_start_day'=>1,'working_days_method'=>'calendar_days','default_working_days'=>30,
            'absent_deduction_method'=>'per_day','deduction_basis'=>'basic_salary','half_day_deduction_percentage'=>50,
            'late_deduction_method'=>'none','late_count_threshold'=>3,'overtime_calculation_method'=>'hourly_rate',
            'overtime_basis'=>'employee_rate','overtime_rate_multiplier'=>1.5,'rounding_method'=>'nearest',
            'allow_negative_salary'=>false,'lock_paid_payroll'=>true,'allow_non_current_month_payroll'=>false,
            'currency'=>'BDT','status'=>true,
        ]);
        $this->attendanceSetting = AttendanceSetting::firstOrCreate([], [
            'grace_minutes'=>10,'half_day_after_minutes'=>240,'absent_after_minutes'=>480,'minimum_overtime_minutes'=>30,
            'default_working_hours'=>8,'weekly_off_days'=>[],'allow_manual_attendance'=>true,
            'auto_calculate_late'=>true,'auto_calculate_overtime'=>true,'status'=>true,
        ]);
        $this->reloadComponents();
    }

    private function reloadComponents(): void
    {
        $this->salaryComponents = SalaryComponent::where('status', true)
            ->orderByRaw("CASE component_group WHEN 'salary' THEN 1 WHEN 'allowance' THEN 2 ELSE 3 END")
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function period(string $month): array
    {
        $date=Carbon::createFromFormat('Y-m',$month)->startOfMonth();
        return [$date->copy()->startOfMonth(),$date->copy()->endOfMonth()];
    }

    public function eligibleEmployeesQuery(Carbon $start, Carbon $end): Builder
    {
        return Employee::query()->whereDate('join_date','<=',$end)
            ->where(fn(Builder $q)=>$q->whereNull('exit_date')->orWhereDate('exit_date','>=',$start));
    }

    public function precheck(string $month): array
    {
        [$start,$end]=$this->period($month);
        $employees=$this->eligibleEmployeesQuery($start,$end)->with(['department','designation'])->orderBy('employee_code')->get();
        $missingSalary=[];$missingAttendance=0;$missingRules=[];
        foreach($employees as $employee){
            $structure=$this->salaryStructureFor($employee,$end);
            if(!$structure){
                $missingSalary[]=[
                    'id'=>$employee->id,
                    'code'=>$employee->employee_code,
                    'name'=>$employee->name,
                    'reason'=>$this->salaryMissingReason($employee,$end),
                ];
            } else foreach($this->missingRulesFor($structure) as $rule){$missingRules[]=['employee_id'=>$employee->id,'employee_code'=>$employee->employee_code,'employee_name'=>$employee->name,'component'=>$rule];}
            $missingAttendance+=$this->missingAttendanceCount($employee,$start,$end);
        }
        $pending=LeaveRequest::where('status','pending')->whereDate('from_date','<=',$end)->whereDate('to_date','>=',$start)->count();
        return [
            'month'=>$start->format('Y-m'),'month_label'=>$start->format('F Y'),'period_start'=>$start->toDateString(),'period_end'=>$end->toDateString(),
            'eligible_employees'=>$employees->count(),'salary_ready'=>$employees->count()-count($missingSalary),'missing_salary_count'=>count($missingSalary),'missing_salary'=>$missingSalary,
            'missing_rule_count'=>count($missingRules),'missing_rules'=>$missingRules,'missing_attendance_count'=>$missingAttendance,'pending_leave_count'=>$pending,
        ];
    }

    public function precheckEmployee(Employee $employee,string $month): array
    {
        [$start,$end]=$this->period($month);
        $eligible=$employee->join_date && $employee->join_date->lte($end) && (!$employee->exit_date || $employee->exit_date->gte($start));
        $structure=$eligible?$this->salaryStructureFor($employee,$end):null;
        $missingRules=$structure?$this->missingRulesFor($structure):[];
        $pending=$eligible?LeaveRequest::where('employee_id',$employee->id)->where('status','pending')->whereDate('from_date','<=',$end)->whereDate('to_date','>=',$start)->count():0;
        return [
            'month'=>$start->format('Y-m'),'month_label'=>$start->format('F Y'),'period_start'=>$start->toDateString(),'period_end'=>$end->toDateString(),
            'employee_id'=>$employee->id,'employee_code'=>$employee->employee_code,'employee_name'=>$employee->name,'eligible'=>$eligible,'salary_ready'=>(bool)$structure,
            'salary_message'=>$eligible && !$structure ? $this->salaryMissingReason($employee,$end) : null,
            'missing_rule_count'=>count($missingRules),'missing_rules'=>$missingRules,'missing_attendance_count'=>$eligible?$this->missingAttendanceCount($employee,$start,$end):0,'pending_leave_count'=>$pending,
        ];
    }

    public function calculate(Employee $employee,Carbon $start,Carbon $end,array $manualComponentAmounts=[]): array
    {
        $this->reloadComponents();
        $structure=$this->salaryStructureFor($employee,$end);
        if(!$structure) throw new RuntimeException($this->salaryMissingReason($employee,$end));
        $missing=$this->missingRulesFor($structure);
        if($missing) throw new RuntimeException('Payroll rule is not configured: '.implode(', ',$missing).'. Update HR Settings or Employee payroll setup.');

        $employmentStart=$employee->join_date && $employee->join_date->gt($start)?$employee->join_date->copy()->startOfDay():$start->copy();
        $employmentEnd=$employee->exit_date && $employee->exit_date->lt($end)?$employee->exit_date->copy()->startOfDay():$end->copy();
        $monthDays=max(1,$start->daysInMonth);$payableCalendarDays=max(0,$employmentStart->diffInDays($employmentEnd)+1);$factor=min(1,$payableCalendarDays/$monthDays);
        $fullBasic=(float)$structure->basic_salary;$proratedBasic=round($fullBasic*$factor,2);

        $attendanceRows=Attendance::where('employee_id',$employee->id)->whereBetween('attendance_date',[$employmentStart,$employmentEnd])->get();
        $statusCounts=$attendanceRows->countBy('status');
        $unpaidLeaveDays=$this->unpaidLeaveDays($employee,$employmentStart,$employmentEnd);
        $paidLeaveDays=max(0,(float)($statusCounts['leave']??0)-$unpaidLeaveDays);
        $expectedWorkingDays=max(1,$this->expectedWorkingDates($employee,$employmentStart,$employmentEnd)->count());
        $salaryDivisor=match($this->payrollSetting->working_days_method){'fixed_days'=>max(1,(float)$this->payrollSetting->default_working_days),'attendance_days'=>$expectedWorkingDays,default=>$monthDays};
        $dailyBasic=$salaryDivisor>0?$fullBasic/$salaryDivisor:0;
        $hoursPerDay=max(1,(float)$this->attendanceSetting->default_working_hours);
        $basicHourly=$dailyBasic/$hoursPerDay;

        $ot=$this->splitOvertimeMinutes($employee,$attendanceRows,$employmentStart,$employmentEnd);
        $recovery=$this->recoveryService->preview($employee,$start);
        $components=[];$runningSalary=0.0;$runningAllowance=0.0;

        foreach($this->salaryComponents as $master){
            $code=strtoupper((string)($master->code?:'COMP-'.$master->id));
            $group=$master->display_group;
            if($code==='BASIC'){
                $row=$this->componentRow($master,'salary','employee_salary',$fullBasic,$factor,$proratedBasic,false,(int)$master->sort_order);
                $components[]=$row;$runningSalary+=(float)$row['amount'];continue;
            }

            $ruleCode=strtolower((string)($master->rule_code?:'standard'));

            // Manual-at-payroll components are intentionally not part of the employee master setup.
            // Their value is supplied for the current payroll only and stored in the payroll snapshot.
            if($master->calculation_type==='manual' && !in_array($ruleCode,['salary_advance','loan_adjustment'],true)){
                $amount=round(max(0,(float)($manualComponentAmounts[$master->id]??0)),2);
                if(abs($amount)<.005 && !$master->show_zero_on_payslip) continue;
                $row=$this->componentRow($master,$group,'payroll_manual',$amount,$amount>0?1:0,$amount,true,(int)$master->sort_order,'manual');
                $components[]=$row;
                if($group==='salary')$runningSalary+=(float)$row['amount']; elseif($group==='allowance')$runningAllowance+=(float)$row['amount'];
                continue;
            }

            $rule=$this->resolveRule($master,$structure);
            if(!$rule['applicable']) continue;
            $amount=0.0;$rate=0.0;$qty=1.0;$calcType=$rule['calculation_type'];$manual=false;$source=$rule['source'];

            if($ruleCode==='ot_day_off' || $ruleCode==='ot_gov_off'){
                $minutes=$ruleCode==='ot_gov_off'?$ot['gov']:$ot['day_off'];$qty=round($minutes/60,4);
                if($calcType==='percentage'){$rate=round($basicHourly*((float)$rule['percentage']/100),4);}else{$rate=(float)$rule['amount'];}
                $amount=round($rate*$qty,2);$calcType='overtime_hours';
            } elseif($ruleCode==='late'){
                $lateDays=(float)($statusCounts['late']??0);$qty=$this->lateEquivalentDays($lateDays);
                $deductionBase=$this->payrollSetting->deduction_basis==='gross_salary'?max(0,$runningSalary+$runningAllowance):$fullBasic;
                $dailyDeductionRate=$salaryDivisor>0?$deductionBase/$salaryDivisor:0;
                if($calcType==='percentage'){$rate=round($dailyDeductionRate*((float)$rule['percentage']/100),4);}else{$rate=(float)$rule['amount'];}
                $amount=round($rate*$qty,2);$calcType='late_rule';
            } elseif($ruleCode==='lwp_absent'){
                $absent=(float)($statusCounts['absent']??0);$half=(float)($statusCounts['half_day']??0);$halfFactor=max(0,min(100,(float)$this->payrollSetting->half_day_deduction_percentage))/100;
                $automaticAbsent=$this->payrollSetting->absent_deduction_method==='per_day'?$absent:0;
                $qty=$automaticAbsent+$unpaidLeaveDays+($half*$halfFactor);
                $deductionBase=$this->payrollSetting->deduction_basis==='gross_salary'?max(0,$runningSalary+$runningAllowance):$fullBasic;
                $dailyDeductionRate=$salaryDivisor>0?$deductionBase/$salaryDivisor:0;
                if($calcType==='percentage'){$rate=round($dailyDeductionRate*((float)$rule['percentage']/100),4);}else{$rate=(float)$rule['amount'];}
                $amount=round($rate*$qty,2);$calcType='attendance_leave_rule';
            } elseif($ruleCode==='salary_advance'){
                $amount=(float)$recovery['salary_advance'];$rate=$amount;$qty=$amount>0?1:0;$calcType='auto_recovery';$manual=false;$source='salary_advance';
            } elseif($ruleCode==='loan_adjustment'){
                $amount=(float)$recovery['loan'];$rate=$amount;$qty=$amount>0?1:0;$calcType='auto_recovery';$manual=false;$source='loan';
            } elseif($calcType==='fixed'){
                $rate=(float)$rule['amount'];$qty=$group==='salary'?$factor:1;$amount=round($rate*$qty,2);
            } elseif($calcType==='percentage'){
                $rate=(float)$rule['percentage'];$base=$master->percentage_of==='gross_salary'?($runningSalary+$runningAllowance):$proratedBasic;$qty=$base;$amount=round($base*$rate/100,2);
            }

            if(abs($amount)<.005 && !$master->show_zero_on_payslip) continue;
            $row=$this->componentRow($master,$group,$source,$rate,$qty,$amount,$manual,(int)$master->sort_order,$calcType);
            $components[]=$row;
            if($group==='salary')$runningSalary+=(float)$row['amount']; elseif($group==='allowance')$runningAllowance+=(float)$row['amount'];
        }

        $salaryTotal=round((float)collect($components)->where('component_group','salary')->sum('amount'),2);
        $allowanceTotal=round((float)collect($components)->where('component_group','allowance')->sum('amount'),2);
        $deduction=round((float)collect($components)->where('component_group','deduction')->sum('amount'),2);
        $gross=round($salaryTotal+$allowanceTotal,2);$net=$this->roundNet($gross-$deduction);if(!$this->payrollSetting->allow_negative_salary)$net=max(0,$net);
        $overtimeMinutes=(int)($ot['day_off']+$ot['gov']);

        return [
            'item'=>[
                'employee_id'=>$employee->id,'employee_salary_structure_id'=>$structure->id,'employee_code'=>$employee->employee_code,'employee_name'=>$employee->name,
                'department_name'=>$employee->department?->name,'designation_name'=>$employee->designation?->name,'joining_date'=>$employee->join_date?->toDateString(),'exit_date'=>$employee->exit_date?->toDateString(),
                'salary_divisor'=>$salaryDivisor,'payable_days'=>$payableCalendarDays,'basic_salary'=>$fullBasic,'prorated_basic_salary'=>$proratedBasic,'salary_total'=>$salaryTotal,'allowance_total'=>$allowanceTotal,
                'gross_salary'=>$gross,'total_deduction'=>$deduction,'net_salary'=>$net,'present_days'=>(float)($statusCounts['present']??0),'late_days'=>(float)($statusCounts['late']??0),'absent_days'=>(float)($statusCounts['absent']??0),
                'half_days'=>(float)($statusCounts['half_day']??0),'paid_leave_days'=>$paidLeaveDays,'unpaid_leave_days'=>$unpaidLeaveDays,'off_days'=>(float)($statusCounts['off_day']??0),'overtime_minutes'=>$overtimeMinutes,
                'attendance_summary'=>['present'=>(float)($statusCounts['present']??0),'late'=>(float)($statusCounts['late']??0),'absent'=>(float)($statusCounts['absent']??0),'half_day'=>(float)($statusCounts['half_day']??0),'paid_leave'=>$paidLeaveDays,'unpaid_leave'=>$unpaidLeaveDays,'off_day'=>(float)($statusCounts['off_day']??0),'not_marked'=>$this->missingAttendanceCount($employee,$employmentStart,$employmentEnd),'overtime_minutes'=>$overtimeMinutes,'ot_day_off_minutes'=>$ot['day_off'],'ot_gov_off_minutes'=>$ot['gov']],
                'payment_method'=>$structure->payment_method,'account_name'=>$structure->account_name,'account_number'=>$structure->account_number,'mobile_banking_provider'=>$structure->mobile_banking_provider,
                'payment_status'=>'unpaid','status'=>'draft','approved_by'=>null,'approved_at'=>null,
            ],
            'components'=>$components,'recovery_allocations'=>$recovery['allocations'],
        ];
    }

    public function settings(): PayrollSetting { return $this->payrollSetting; }

    public function manualPayrollComponents(): Collection
    {
        return $this->salaryComponents
            ->filter(function($component){
                $ruleCode=strtolower((string)($component->rule_code?:'standard'));
                return $component->calculation_type==='manual' && !in_array($ruleCode,['salary_advance','loan_adjustment'],true);
            })
            ->values();
    }

    private function missingRulesFor(EmployeeSalaryStructure $structure): array
    {
        $missing=[];
        foreach($this->salaryComponents as $master){
            if(strtoupper((string)$master->code)==='BASIC') continue;
            $ruleCode=strtolower((string)($master->rule_code?:'standard'));
            if($master->calculation_type==='manual' || in_array($ruleCode,['salary_advance','loan_adjustment'],true)) continue;
            $rule=$this->resolveRule($master,$structure);
            if($rule['applicable'] && !$rule['configured']) $missing[]=$master->display_label;
        }
        return array_values(array_unique($missing));
    }

    private function resolveRule(SalaryComponent $master,EmployeeSalaryStructure $structure): array
    {
        $employeeRule=$structure->components->firstWhere('salary_component_id',$master->id);
        if($employeeRule && $master->allow_employee_override){
            $mode=$employeeRule->rule_mode?:'custom';
            if($mode==='disabled') return ['applicable'=>false,'configured'=>true,'source'=>'employee_disabled','calculation_type'=>$master->calculation_type,'amount'=>0,'percentage'=>0];
            if($mode==='custom') return ['applicable'=>true,'configured'=>true,'source'=>'employee','calculation_type'=>$master->calculation_type,'amount'=>(float)$employeeRule->amount,'percentage'=>(float)$employeeRule->percentage];
        }
        if(!$master->apply_to_all && !$employeeRule) return ['applicable'=>false,'configured'=>true,'source'=>'not_applicable','calculation_type'=>$master->calculation_type,'amount'=>0,'percentage'=>0];
        return ['applicable'=>true,'configured'=>(bool)$master->global_configured,'source'=>'global','calculation_type'=>$master->calculation_type,'amount'=>(float)$master->default_amount,'percentage'=>(float)$master->default_percentage];
    }

    private function salaryStructureFor(Employee $employee,Carbon $date): ?EmployeeSalaryStructure
    {
        return EmployeeSalaryStructure::with('components.salaryComponent')->where('employee_id',$employee->id)->where('status',true)->whereDate('effective_from','<=',$date)
            ->where(fn(Builder $q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$date))->latest('effective_from')->first();
    }

    private function salaryMissingReason(Employee $employee,Carbon $date): string
    {
        $future=EmployeeSalaryStructure::where('employee_id',$employee->id)->where('status',true)
            ->whereDate('effective_from','>',$date)->orderBy('effective_from')->first();
        if($future){
            return "Salary is configured from {$future->effective_from->format('d-m-Y')}, which starts after the selected payroll month for {$employee->employee_code}. Change Effective From to the employee joining date if salary should apply from joining.";
        }

        $latest=EmployeeSalaryStructure::where('employee_id',$employee->id)->where('status',true)
            ->whereDate('effective_from','<=',$date)->latest('effective_from')->first();
        if($latest && $latest->effective_to && $latest->effective_to->lt($date)){
            return "Salary setup for {$employee->employee_code} ends on {$latest->effective_to->format('d-m-Y')} and does not cover the selected payroll month.";
        }

        return "Salary setup is missing for {$employee->employee_code} in the selected payroll month.";
    }

    private function splitOvertimeMinutes(Employee $employee,Collection $rows,Carbon $start,Carbon $end): array
    {
        if(!($this->attendanceSetting->auto_calculate_overtime??true)) return ['day_off'=>0,'gov'=>0];

        $holidays=Holiday::where('status',true)->whereBetween('holiday_date',[$start,$end])
            ->get(['holiday_date','holiday_type'])
            ->keyBy(fn($holiday)=>Carbon::parse($holiday->holiday_date)->toDateString());
        $rosterOff=ShiftRoster::where('employee_id',$employee->id)->whereBetween('roster_date',[$start,$end])->where('status','off')
            ->pluck('roster_date')->map(fn($d)=>Carbon::parse($d)->toDateString())->flip();
        $weeklyOff=collect($this->attendanceSetting->weekly_off_days??[])->map(fn($d)=>strtolower((string)$d));
        $minimum=max(0,(int)($this->attendanceSetting->minimum_overtime_minutes??0));
        $dayOff=0;$gov=0;

        foreach($rows as $row){
            $date=Carbon::parse($row->attendance_date);$key=$date->toDateString();$holiday=$holidays->get($key);
            $isGov=$holiday && strtolower((string)$holiday->holiday_type)==='public';
            $isOtherHoliday=$holiday && !$isGov;
            $isDayOff=$isOtherHoliday||$rosterOff->has($key)||$weeklyOff->contains(strtolower($date->format('l')))||$row->status==='off_day';
            if(!$isGov&&!$isDayOff) continue;

            // On a weekly/roster/public holiday the whole worked duration is OT, not only time after a normal shift.
            $minutes=max(0,(int)($row->worked_minutes??0));
            if($minutes<=0)$minutes=max(0,(int)$row->overtime_minutes);
            if($minutes<$minimum)continue;
            if($isGov)$gov+=$minutes;else$dayOff+=$minutes;
        }
        return ['day_off'=>$dayOff,'gov'=>$gov];
    }

    private function expectedWorkingDates(Employee $employee,Carbon $start,Carbon $end): Collection
    {
        $weeklyOff=collect($this->attendanceSetting->weekly_off_days??[])->map(fn($d)=>strtolower((string)$d));
        $holidays=Holiday::where('status',true)->whereBetween('holiday_date',[$start,$end])->pluck('holiday_date')->map(fn($d)=>Carbon::parse($d)->toDateString())->flip();
        $rosterOff=ShiftRoster::where('employee_id',$employee->id)->whereBetween('roster_date',[$start,$end])->where('status','off')->pluck('roster_date')->map(fn($d)=>Carbon::parse($d)->toDateString())->flip();
        return collect(CarbonPeriod::create($start,$end))->filter(fn(Carbon $d)=>!$weeklyOff->contains(strtolower($d->format('l')))&&!$holidays->has($d->toDateString())&&!$rosterOff->has($d->toDateString()))->values();
    }

    private function missingAttendanceCount(Employee $employee,Carbon $start,Carbon $end): int
    {
        $checkEnd=$end->isFuture()?now()->startOfDay():$end->copy();if($checkEnd->lt($start))return 0;
        $expected=$this->expectedWorkingDates($employee,$start,$checkEnd);$marked=Attendance::where('employee_id',$employee->id)->whereBetween('attendance_date',[$start,$checkEnd])->pluck('attendance_date')->map(fn($d)=>Carbon::parse($d)->toDateString())->flip();$leave=$this->approvedLeaveDates($employee,$start,$checkEnd);
        return $expected->filter(fn(Carbon $d)=>!$marked->has($d->toDateString())&&!$leave->has($d->toDateString()))->count();
    }

    private function approvedLeaveDates(Employee $employee,Carbon $start,Carbon $end,?bool $paid=null): Collection
    {
        $q=LeaveRequest::where('employee_id',$employee->id)->where('status','approved')->whereDate('from_date','<=',$end)->whereDate('to_date','>=',$start);if($paid!==null)$q->where('is_paid',$paid);
        $dates=collect();foreach($q->get() as $leave){$from=Carbon::parse($leave->from_date)->startOfDay();$to=Carbon::parse($leave->to_date)->startOfDay();$from=$from->gt($start)?$from:$start->copy();$to=$to->lt($end)?$to:$end->copy();foreach(CarbonPeriod::create($from,$to) as $d)$dates->put($d->toDateString(),true);}return $dates;
    }

    private function unpaidLeaveDays(Employee $employee,Carbon $start,Carbon $end): float
    {
        $unpaid=$this->approvedLeaveDates($employee,$start,$end,false);$expected=$this->expectedWorkingDates($employee,$start,$end)->map(fn(Carbon $d)=>$d->toDateString())->flip();return (float)$unpaid->keys()->filter(fn($d)=>$expected->has($d))->count();
    }

    private function lateEquivalentDays(float $lateCount): float
    {
        $threshold=max(1,(int)$this->payrollSetting->late_count_threshold);$groups=floor($lateCount/$threshold);
        return match($this->payrollSetting->late_deduction_method){'half_day_after_count'=>$groups*.5,'full_day_after_count'=>$groups,default=>0};
    }

    private function roundNet(float $amount): float
    {
        return match($this->payrollSetting->rounding_method){'nearest'=>round($amount),'floor'=>floor($amount),'ceil'=>ceil($amount),default=>round($amount,2)};
    }

    private function componentRow(SalaryComponent $master,string $group,string $source,float $rate,float $quantity,float $amount,bool $manual,int $sortOrder,?string $calcType=null): array
    {
        return ['salary_component_id'=>$master->id,'component_name'=>$master->display_label,'component_code'=>$master->code,'component_type'=>$group==='deduction'?'deduction':'earning','component_group'=>$group,'source'=>$source,
            'calculation_type'=>$calcType?:$master->calculation_type,'rate'=>round($rate,4),'quantity'=>round($quantity,4),'calculated_amount'=>round($amount,2),'amount'=>round($amount,2),'is_manual'=>$manual,'is_overridden'=>false,'override_reason'=>null,'sort_order'=>$sortOrder];
    }
}
