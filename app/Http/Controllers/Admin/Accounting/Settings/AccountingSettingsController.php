<?php
namespace App\Http\Controllers\Admin\Accounting\Settings;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** Settings accounting module. A shared base provides posting dependencies and invariant checks. */
final class AccountingSettingsController extends \App\Http\Controllers\Admin\Accounting\Shared\FoundationController
{
    public function settings()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-settings');
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        $history=DB::table('accounting_setting_histories')->orderByDesc('id')->paginate(20, ['*'], 'acc_settings_1')->withQueryString();
        $accounts=DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)->orderBy('code')->get();
        return view('admin.accounting.settings.index',compact('settings','history','accounts'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveSettings(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->canDo('accounting-settings');
        $data=$r->validate(['capture_enabled'=>'required|boolean','posting_mode'=>'required|in:manual,auto',
            'locked_until'=>'nullable|date','rounding_account_id'=>'nullable|exists:chart_of_accounts,id',
            'valuation_method'=>'required|in:weighted_average','reason'=>'required|string|min:8|max:500']);
        DB::transaction(function () use($data) {
            $old=DB::table('accounting_settings')->where('id',1)->lockForUpdate()->first();
            if (!$old) throw ValidationException::withMessages(['settings'=>'Accounting migration has not been run.']);
            $new=[
                'capture_enabled'=>(int)$data['capture_enabled'],
                'posting_mode'=>$data['posting_mode'],
                'locked_until'=>$data['locked_until'] ?? null,
                'rounding_account_id'=>$data['rounding_account_id'] ?? null,
                'valuation_method'=>$data['valuation_method'],
            ];
            if (!empty($new['rounding_account_id'])) {
                $a=DB::table('chart_of_accounts')->find($new['rounding_account_id']);
                if (!$a || !$a->is_active || $a->is_header) throw ValidationException::withMessages(['rounding_account_id'=>'Select a valid active posting account.']);
            }
            if ($new['capture_enabled'] && !$old->capture_enabled) {
                // Strict start fence: no historical operational transaction auto backfill.
                $new['activated_at']=now();
            }
            foreach ($new as $field=>$value) {
                $oldValue=$old->$field;
                if ((string)($oldValue ?? '') !== (string)($value ?? '')) {
                    DB::table('accounting_setting_histories')->insert([
                        'user_id'=>auth()->id(),'field'=>$field,'old_value'=>(string)($oldValue ?? ''),
                        'new_value'=>(string)($value ?? ''),'reason'=>$data['reason'],'changed_at'=>now(),
                    ]);
                }
            }
            DB::table('accounting_settings')->where('id',1)->update($new+['updated_by'=>auth()->id(),'updated_at'=>now()]);
        });
        return back()->with('success','Accounting settings saved with audit history.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
