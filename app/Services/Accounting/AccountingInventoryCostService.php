<?php
namespace App\Services\Accounting;

use App\Models\{Purchase,StockMovement,StockLocation};
use App\Services\Inventory\DecimalQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Verified WAC cost ONLY. Inventory quantity engine remains authoritative and unchanged. */
final class AccountingInventoryCostService
{
    public function __construct(private AccountingEventService $events, private DecimalQuantity $decimal) {}

    public function captureMovement(int $movementId): void
    {
        $settings=DB::table('accounting_settings')->where('id',1)->first();
        if (!$settings || !$settings->capture_enabled || !$settings->activated_at) return;
        $movement=StockMovement::query()->with('items')->find($movementId);
        if (!$movement || $movement->status!==StockMovement::STATUS_POSTED) return;
        // Opening/physical adjustments must never invent a monetary cost.
        if (!in_array($movement->movement_type, [
            StockMovement::PURCHASE_RECEIVE, StockMovement::MAIN_TO_KITCHEN,
            StockMovement::KITCHEN_TO_MAIN, StockMovement::ORDER_CONSUMPTION,
            StockMovement::WASTAGE, StockMovement::PURCHASE_RETURN,
        ],true)) return;
        DB::transaction(function () use($movement) {
            foreach ($movement->items as $item) {
                $existing=DB::table('inventory_cost_movements')
                    ->where('stock_movement_id',$movement->id)->where('ingredient_id',$item->ingredient_id)->first();
                if ($existing) continue;
                $qty=$this->decimal->normalize((string)$item->quantity_base);
                $verifiedQty=$this->decimal->normalize('0'); $verifiedAmount=0;
                $source=(int)$movement->source_location_id; $destination=(int)$movement->destination_location_id;
                if ($movement->movement_type===StockMovement::PURCHASE_RECEIVE) {
                    $purchase=Purchase::query()->where('received_stock_movement_id',$movement->id)->first();
                    $row=$purchase ? DB::table('purchase_items')->where('purchase_id',$purchase->id)
                        ->where('ingredient_id',$item->ingredient_id)->first() : null;
                    $subtotal=$purchase ? DB::table('purchase_items')->where('purchase_id',$purchase->id)->sum('line_total') : null;
                    if ($purchase && $row && $this->equalMoney((string)$subtotal,(string)$purchase->total)
                        && $this->decimal->compare($qty,(string)$row->base_quantity)===0) {
                        $verifiedQty=$qty;
                        $verifiedAmount=AccountingMoney::units((string)$row->line_total);
                        $this->increase($destination,$item->ingredient_id,$qty,$verifiedAmount);
                    } else {
                        $this->pending($movement,$item->ingredient_id,$qty,'GRN invoice or line valuation requires verification.');
                    }
                } else {
                    [$verifiedQty,$verifiedAmount]=$this->consume($source,$item->ingredient_id,$qty);
                    if ($destination && $this->decimal->compare($verifiedQty,'0')>0) {
                        $this->increase($destination,$item->ingredient_id,$verifiedQty,$verifiedAmount);
                    }
                    $missing=$this->decimal->subtract($qty,$verifiedQty);
                    if ($this->decimal->compare($missing,'0')>0) {
                        $this->pending($movement,$item->ingredient_id,$missing,'Negative/unverified inventory: no reliable source cost for this quantity.');
                    }
                }
                DB::table('inventory_cost_movements')->insert([
                    'stock_movement_id'=>$movement->id,'ingredient_id'=>$item->ingredient_id,
                    'movement_type'=>$movement->movement_type,'verified_qty'=>$verifiedQty,
                    'verified_cost'=>AccountingMoney::format($verifiedAmount),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        },5);
        // Separate balanced event for the verified portion. Never force-post unverified COGS.
        $cost=0;
        foreach (DB::table('inventory_cost_movements')->where('stock_movement_id',$movementId)->get() as $row) {
            $cost+=AccountingMoney::units((string)$row->verified_cost);
        }
        if ($cost===0 || in_array($movement->movement_type,[StockMovement::PURCHASE_RECEIVE,StockMovement::PURCHASE_RETURN],true)) return;
        $event=match($movement->movement_type) {
            StockMovement::MAIN_TO_KITCHEN=>'INTERNAL_TRANSFER',
            StockMovement::KITCHEN_TO_MAIN=>'INTERNAL_RETURN',
            StockMovement::ORDER_CONSUMPTION=>'COGS_VERIFIED',
            StockMovement::WASTAGE => $this->isKitchen((int)$movement->source_location_id)
                ? 'WASTAGE_VERIFIED' : 'STORE_WASTAGE_VERIFIED',
            default=>null,
        };
        if (!$event) return;
        $this->events->capture('stock_movements',$movement->id,$event,1,
            (string)$movement->occurred_at,null,[
                'total'=>AccountingMoney::format($cost),
                'amounts'=>['verified_cost'=>AccountingMoney::format($cost)],
                'source'=>['movement_type'=>$movement->movement_type,
                    'source_location_id'=>$movement->source_location_id,
                    'destination_location_id'=>$movement->destination_location_id,
                    'reference_type'=>$movement->reference_type,'reference_id'=>$movement->reference_id],
            ]);
    }

    private function isKitchen(int $locationId): bool
    {
        return strtolower((string)DB::table('stock_locations')->where('id',$locationId)->value('type'))==='kitchen';
    }
    private function equalMoney(string $a,string $b): bool
    {
        return AccountingMoney::units($a)===AccountingMoney::units($b);
    }
    private function balance(int $locationId,int $ingredientId): object
    {
        if ($locationId<1) throw new \RuntimeException('Stock location is mandatory for verified costing.');
        DB::table('inventory_cost_balances')->insertOrIgnore([
            'stock_location_id'=>$locationId,'ingredient_id'=>$ingredientId,
            'verified_qty'=>'0','verified_value'=>'0','created_at'=>now(),'updated_at'=>now(),
        ]);
        return DB::table('inventory_cost_balances')->where('stock_location_id',$locationId)
            ->where('ingredient_id',$ingredientId)->lockForUpdate()->first();
    }
    private function increase(int $locationId,int $ingredientId,string $qty,int $units): void
    {
        $row=$this->balance($locationId,$ingredientId);
        $newQty=$this->decimal->add((string)$row->verified_qty,$qty);
        $newValue=AccountingMoney::units((string)$row->verified_value)+$units;
        DB::table('inventory_cost_balances')->where('id',$row->id)->update([
            'verified_qty'=>$newQty,'verified_value'=>AccountingMoney::format($newValue),'updated_at'=>now(),
        ]);
    }
    /** Returns verified quantity and cost; never divides by a negative/zero inventory balance. */
    private function consume(int $locationId,int $ingredientId,string $requested): array
    {
        $row=$this->balance($locationId,$ingredientId);
        $available=$this->decimal->normalize((string)$row->verified_qty);
        $currentCost=AccountingMoney::units((string)$row->verified_value);
        if ($this->decimal->compare($available,'0')<=0 || $currentCost<=0) return ['0.00000000',0];
        $qty=$this->decimal->compare($available,$requested)>0 ? $requested : $available;
        if ($this->decimal->compare($available,$qty)===0) $units=$currentCost;
        else {
            $average=$this->decimal->divide(AccountingMoney::format($currentCost),$available,12);
            $provisional=$this->decimal->multiply($average,$qty,4);
            $units=min($currentCost,AccountingMoney::units($provisional));
        }
        if ($units===0) return ['0.00000000',0];
        $nextQty=$this->decimal->subtract($available,$qty);
        DB::table('inventory_cost_balances')->where('id',$row->id)->update([
            'verified_qty'=>$nextQty,'verified_value'=>AccountingMoney::format($currentCost-$units),
            'updated_at'=>now(),
        ]);
        return [$qty,$units];
    }
    private function pending(StockMovement $m,int $ingredient,string $quantity,string $reason): void
    {
        DB::table('negative_inventory_cost_pending')->insertOrIgnore([
            'stock_movement_id'=>$m->id,'ingredient_id'=>$ingredient,
            'order_id'=>$m->movement_type===StockMovement::ORDER_CONSUMPTION ? $m->reference_id : null,
            'stock_location_id'=>$m->source_location_id ?: $m->destination_location_id,
            'quantity_base'=>$quantity,'status'=>'COST_PENDING','reason'=>$reason,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    /** Accountant requests review, not an automatic GL post. Verifiable cost proof is mandatory. */
    public function submitCostVerification(int $id,string $cost,string $reason,?int $actor): int
    {
        if (trim($reason)==='' || mb_strlen(trim($reason))<10) throw new \InvalidArgumentException('Cost evidence/reason (10+ chars) required.');
        $units=AccountingMoney::units($cost); if ($units===0) throw new \InvalidArgumentException('Verified cost must be positive.');
        return DB::transaction(function () use($id,$units,$reason,$actor) {
            $row=DB::table('negative_inventory_cost_pending')->where('id',$id)->lockForUpdate()->first();
            if (!$row || $row->status!=='COST_PENDING') throw new \RuntimeException('Cost already submitted or not pending.');
            $movement=DB::table('stock_movements')->where('id',$row->stock_movement_id)->first();
            if (!$movement || $movement->movement_type!==StockMovement::ORDER_CONSUMPTION) {
                throw new \RuntimeException('Only verified order consumption cost can be submitted here; receipt/transfer requires valuation reconciliation.');
            }
            $balance=$this->balance((int)$row->stock_location_id,(int)$row->ingredient_id);
            $have=$this->decimal->normalize((string)$balance->verified_qty);
            if ($this->decimal->compare($have,(string)$row->quantity_base)<0) {
                throw new \RuntimeException('Verified kitchen cost quantity is not yet available. Match a GRN/assign movement before confirming.');
            }
            $total=AccountingMoney::units((string)$balance->verified_value);
            $estimated=$this->decimal->compare($have,(string)$row->quantity_base)===0
                ? $total
                : AccountingMoney::units($this->decimal->multiply(
                    $this->decimal->divide(AccountingMoney::format($total),$have,12),
                    (string)$row->quantity_base,4));
            if ($units!==$estimated) {
                throw new \RuntimeException('Verified cost must match the available weighted-average kitchen inventory value.');
            }
            $e=$this->events->capture('negative_inventory_cost_pending',$row->id,'PENDING_COST_VERIFIED',1,
                (string)now(),null,['total'=>AccountingMoney::format($units),
                'amounts'=>['verified_cost'=>AccountingMoney::format($units)],
                'source'=>['movement_id'=>$row->stock_movement_id,'ingredient_id'=>$row->ingredient_id,
                    'quantity_base'=>$row->quantity_base,'evidence'=>$reason]]);
            if (!$e) throw new \RuntimeException('Capture OFF: cost verification cannot create an accounting event.');
            DB::table('negative_inventory_cost_pending')->where('id',$id)->update([
                'verified_cost'=>AccountingMoney::format($units),'verified_by'=>$actor,'verified_at'=>now(),
                'accounting_event_id'=>$e,'status'=>'WAITING_POST','reason'=>$reason,'updated_at'=>now(),
            ]);
            return $e;
        },3);
    }
}
