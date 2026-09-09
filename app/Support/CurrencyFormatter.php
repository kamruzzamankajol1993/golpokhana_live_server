<?php

namespace App\Support;

final class CurrencyFormatter
{
    /**
     * Format an amount using Bangladesh/Indian digit grouping.
     * Examples: 10000 => 10,000; 1100000 => 11,00,000.
     */
    public static function bdAmount($value, int $decimals = 0): string
    {
        $number = (float) ($value ?? 0);
        $negative = $number < 0;
        $number = abs($number);

        $fixed = number_format($number, max(0, $decimals), '.', '');
        [$whole, $fraction] = array_pad(explode('.', $fixed, 2), 2, '');

        if (strlen($whole) > 3) {
            $lastThree = substr($whole, -3);
            $leading = substr($whole, 0, -3);
            $leading = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $leading);
            $whole = $leading . ',' . $lastThree;
        }

        $formatted = $whole;
        if ($decimals > 0) {
            $formatted .= '.' . $fraction;
        }

        return ($negative ? '-' : '') . $formatted;
    }
}
