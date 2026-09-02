<?php

namespace App\Services\Inventory;

class InventoryReconciliationCalculator
{
    public function __construct(private DecimalQuantity $decimal)
    {
    }

    public function calculate(array $values): array
    {
        $opening = $this->decimal->normalize((string) ($values['opening'] ?? '0'));
        $transferIn = $this->decimal->normalize((string) ($values['transfer_in'] ?? '0'));
        $consumption = $this->decimal->normalize((string) ($values['consumption'] ?? '0'));
        $wastage = $this->decimal->normalize((string) ($values['wastage'] ?? '0'));
        $returnToMain = $this->decimal->normalize((string) ($values['return_to_main'] ?? '0'));
        $positiveAdjustment = $this->decimal->normalize((string) ($values['positive_adjustment'] ?? '0'));
        $negativeAdjustment = $this->decimal->normalize((string) ($values['negative_adjustment'] ?? '0'));
        $otherDelta = $this->decimal->normalize((string) ($values['other_delta'] ?? '0'));

        $formulaClosing = $opening;
        $formulaClosing = $this->decimal->add($formulaClosing, $transferIn);
        $formulaClosing = $this->decimal->subtract($formulaClosing, $consumption);
        $formulaClosing = $this->decimal->subtract($formulaClosing, $wastage);
        $formulaClosing = $this->decimal->subtract($formulaClosing, $returnToMain);
        $formulaClosing = $this->decimal->add($formulaClosing, $positiveAdjustment);
        $formulaClosing = $this->decimal->subtract($formulaClosing, $negativeAdjustment);

        $ledgerClosing = $this->decimal->add($formulaClosing, $otherDelta);

        return [
            'opening' => $opening,
            'transfer_in' => $transferIn,
            'consumption' => $consumption,
            'wastage' => $wastage,
            'return_to_main' => $returnToMain,
            'positive_adjustment' => $positiveAdjustment,
            'negative_adjustment' => $negativeAdjustment,
            'other_delta' => $otherDelta,
            'formula_closing' => $formulaClosing,
            'ledger_closing' => $ledgerClosing,
            'variance' => $this->decimal->subtract($ledgerClosing, $formulaClosing),
        ];
    }
}
