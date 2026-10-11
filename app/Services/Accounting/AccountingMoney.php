<?php
namespace App\Services\Accounting;

use Illuminate\Validation\ValidationException;

final class AccountingMoney
{
    // Integer in 1/10000 BDT units. No float comparisons in posting validations.
    public static function units(string|int|float $value): int
    {
        $string = trim((string) $value);
        if (!preg_match('/^([0-9]{1,14})(?:\.([0-9]{1,4}))?$/D', $string, $m)) {
            throw ValidationException::withMessages(['amount'=>'Amounts must be nonnegative DECIMAL(18,4).']);
        }
        return ((int)$m[1]) * 10000 + (int)str_pad($m[2] ?? '', 4, '0');
    }

    public static function format(int $units): string
    {
        // Signed balances may appear in reconciliations and reports. Format the
        // absolute subunits first to avoid invalid strings such as 0.-5000.
        $negative = $units < 0;
        $absolute = abs($units);
        return ($negative ? '-' : '').intdiv($absolute, 10000).'.'
            .str_pad((string)($absolute % 10000), 4, '0', STR_PAD_LEFT);
    }
}
