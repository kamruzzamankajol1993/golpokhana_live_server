<?php
namespace App\Http\Controllers\Admin\Accounting\InventoryCost;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** InventoryCost accounting module. A shared base provides posting dependencies and invariant checks. */
final class InventoryCostController extends \App\Http\Controllers\Admin\Accounting\Shared\OperationsController
{
    public function costs()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $pending=DB::table('negative_inventory_cost_pending as p')
            ->join('stock_movements as m','m.id','=','p.stock_movement_id')
            ->select('p.*','m.movement_type','m.movement_no')->orderByDesc('p.id')->paginate(25);
        $balances=DB::table('inventory_cost_balances as b')
            ->leftJoin('ingredients as i','i.id','=','b.ingredient_id')
            ->leftJoin('stock_locations as s','s.id','=','b.stock_location_id')
            ->select('b.*','i.name as ingredient','s.name as location')->orderBy('s.name')->paginate(20, ['*'], 'acc_inventory_cost_1')->withQueryString();
        return view('admin.accounting.inventory-cost.index',compact('pending','balances'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function verifyCost(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-cost-review');
        $d=$r->validate(['verified_cost'=>'required|numeric|gt:0','reason'=>'required|string|min:10|max:3000']);
        $event=$this->costing->submitCostVerification($id,AccountingMoney::format($this->amount($d['verified_cost'])),
            $d['reason'],auth()->id());
        return back()->with('success','Cost verification submitted as event #'.$event.'. GL remains unchanged until journal approval and posting.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
