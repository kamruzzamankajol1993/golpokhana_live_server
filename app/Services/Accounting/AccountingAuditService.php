<?php
namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

final class AccountingAuditService
{
    public static function record(string $subject, ?int $id, string $action,
                                  mixed $before, mixed $after, ?string $reason=null): void
    {
        DB::table('accounting_audit_logs')->insert([
            'subject_type'=>$subject,'subject_id'=>$id,'action'=>$action,'actor_id'=>auth()->id(),
            'before_data'=>$before===null ? null : json_encode($before,JSON_THROW_ON_ERROR),
            'after_data'=>$after===null ? null : json_encode($after,JSON_THROW_ON_ERROR),
            'reason'=>$reason,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
