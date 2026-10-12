<?php
namespace App\Services\Inventory;

use Illuminate\Validation\ValidationException;

/** Tax rate is from the vendor; application is captured in immutable Purchase/Payment snapshots. */
final class VendorWithholdingService
{
    public function rate(mixed $raw): float
    {
        if ($raw === null || trim((string)$raw) === '') return 0.0;
        $value = trim((string)$raw);
        // Legacy vendor data allowed strings, including values such as "5%".
        if (str_ends_with($value, '%')) $value = trim(substr($value, 0, -1));
        if (!preg_match('/^(?:\d{1,3})(?:\.\d{1,4})?$/D', $value) || (float)$value > 100) {
            throw ValidationException::withMessages(['tds'=>'Vendor TDS/VDS percentages must be numeric values from 0 to 100. Correct the Vendor first.']);
        }
        return round((float)$value,4);
    }
    public function calculate(float $gross, float $tdsRate, float $vdsRate, ?float $taxableBase = null): array
    {
        $gross=round($gross,4);
        $base=round($taxableBase ?? $gross,4);
        if ($base<=0 || $base>$gross || $gross<=0 || $tdsRate<0 || $vdsRate<0 || $tdsRate+$vdsRate>=100) {
            throw ValidationException::withMessages(['amount'=>'Gross amount must be positive and the total withholding rate must be below 100%.']);
        }
        $tds=round($base*$tdsRate/100,2);
        $vds=round($base*$vdsRate/100,2);
        $net=round($gross-$tds-$vds,4);
        if ($net<=0) throw ValidationException::withMessages(['amount'=>'Net payable must be greater than zero.']);
        return ['gross'=>$gross,'base'=>$base,'tds'=>$tds,'vds'=>$vds,'net'=>$net];
    }
}
