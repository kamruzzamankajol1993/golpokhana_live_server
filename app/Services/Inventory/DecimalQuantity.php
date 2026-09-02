<?php

namespace App\Services\Inventory;

use InvalidArgumentException;

class DecimalQuantity
{
    public const SCALE = 8;

    public function normalize(string|int $value, int $scale = self::SCALE): string
    {
        $raw = trim((string) $value);
        if (!preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('Quantity must be a valid decimal number.');
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($fraction) > $scale) {
            $roundDigit = (int) $fraction[$scale];
            $fraction = substr($fraction, 0, $scale);
            $digits = $whole . str_pad($fraction, $scale, '0');
            if ($roundDigit >= 5) {
                $digits = $this->addUnsignedOne($digits);
            }
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $whole = substr($digits, 0, -$scale) ?: '0';
            $fraction = $scale > 0 ? substr($digits, -$scale) : '';
        } else {
            $fraction = str_pad($fraction, $scale, '0');
        }

        $fraction = $scale > 0 ? $fraction : '';
        $isZero = $whole === '0' && trim($fraction, '0') === '';
        $prefix = $negative && !$isZero ? '-' : '';

        return $scale > 0 ? $prefix . $whole . '.' . $fraction : $prefix . $whole;
    }

    public function compare(string|int $left, string|int $right, int $scale = self::SCALE): int
    {
        $a = $this->normalize($left, $scale);
        $b = $this->normalize($right, $scale);

        $aNeg = str_starts_with($a, '-');
        $bNeg = str_starts_with($b, '-');
        if ($aNeg !== $bNeg) {
            return $aNeg ? -1 : 1;
        }

        $aDigits = str_replace(['-', '.'], '', $a);
        $bDigits = str_replace(['-', '.'], '', $b);
        $aDigits = ltrim($aDigits, '0') ?: '0';
        $bDigits = ltrim($bDigits, '0') ?: '0';

        $cmp = strlen($aDigits) <=> strlen($bDigits);
        if ($cmp === 0) {
            $cmp = strcmp($aDigits, $bDigits) <=> 0;
        }

        return $aNeg ? -$cmp : $cmp;
    }

    public function add(string|int $left, string|int $right, int $scale = self::SCALE): string
    {
        $a = $this->normalize($left, $scale);
        $b = $this->normalize($right, $scale);

        $aNeg = str_starts_with($a, '-');
        $bNeg = str_starts_with($b, '-');
        $aDigits = str_replace(['-', '.'], '', $a);
        $bDigits = str_replace(['-', '.'], '', $b);
        $width = max(strlen($aDigits), strlen($bDigits));
        $aDigits = str_pad($aDigits, $width, '0', STR_PAD_LEFT);
        $bDigits = str_pad($bDigits, $width, '0', STR_PAD_LEFT);

        if ($aNeg === $bNeg) {
            $digits = $this->addUnsigned($aDigits, $bDigits);
            $negative = $aNeg;
        } else {
            $cmp = $this->compareUnsigned($aDigits, $bDigits);
            if ($cmp === 0) {
                return $this->normalize('0', $scale);
            }
            if ($cmp > 0) {
                $digits = $this->subtractUnsigned($aDigits, $bDigits);
                $negative = $aNeg;
            } else {
                $digits = $this->subtractUnsigned($bDigits, $aDigits);
                $negative = $bNeg;
            }
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $raw = $scale > 0
            ? substr($digits, 0, -$scale) . '.' . substr($digits, -$scale)
            : $digits;

        if ($negative && trim(str_replace('.', '', $raw), '0') !== '') {
            $raw = '-' . $raw;
        }

        return $this->normalize($raw, $scale);
    }

    public function subtract(string|int $left, string|int $right, int $scale = self::SCALE): string
    {
        $right = trim((string) $right);
        $negated = str_starts_with($right, '-') ? ltrim($right, '-') : '-' . ltrim($right, '+');
        return $this->add($left, $negated, $scale);
    }

    public function multiply(string|int $left, string|int $right, int $scale = self::SCALE): string
    {
        [$aNegative, $aDigits, $aScale] = $this->rawParts($left);
        [$bNegative, $bDigits, $bScale] = $this->rawParts($right);

        $product = $this->multiplyUnsigned($aDigits, $bDigits);
        $decimalPlaces = $aScale + $bScale;

        if ($decimalPlaces > 0) {
            $product = str_pad($product, $decimalPlaces + 1, '0', STR_PAD_LEFT);
            $raw = substr($product, 0, -$decimalPlaces) . '.' . substr($product, -$decimalPlaces);
        } else {
            $raw = $product;
        }

        if ($aNegative xor $bNegative) {
            $raw = '-' . $raw;
        }

        return $this->normalize($raw, $scale);
    }

    public function divide(string|int $left, string|int $right, int $scale = self::SCALE): string
    {
        [$aNegative, $aDigits, $aScale] = $this->rawParts($left);
        [$bNegative, $bDigits, $bScale] = $this->rawParts($right);

        if ($bDigits === '0') {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }
        if ($aDigits === '0') {
            return $this->normalize('0', $scale);
        }

        // Calculate one guard digit beyond the requested scale so normalize() can
        // apply the same half-up rounding used by the rest of the decimal engine.
        $guardScale = $scale + 1;
        $numerator = $aDigits . str_repeat('0', $bScale + $guardScale);
        $denominator = $bDigits . str_repeat('0', $aScale);
        $quotient = $this->divideUnsigned($numerator, $denominator);
        $quotient = str_pad($quotient, $guardScale + 1, '0', STR_PAD_LEFT);

        $raw = substr($quotient, 0, -$guardScale) . '.' . substr($quotient, -$guardScale);
        if ($aNegative xor $bNegative) {
            $raw = '-' . $raw;
        }

        return $this->normalize($raw, $scale);
    }

    public function isPositive(string|int $value): bool
    {
        return $this->compare($value, '0') > 0;
    }

    private function rawParts(string|int $value): array
    {
        $raw = trim((string) $value);
        if (!preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('Quantity must be a valid decimal number.');
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $digits = ltrim($whole . $fraction, '0') ?: '0';

        return [$negative, $digits, strlen($fraction)];
    }

    private function multiplyUnsigned(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }

        $result = array_fill(0, strlen($a) + strlen($b), 0);
        for ($i = strlen($a) - 1; $i >= 0; $i--) {
            for ($j = strlen($b) - 1; $j >= 0; $j--) {
                $position = $i + $j + 1;
                $sum = $result[$position] + ((int) $a[$i] * (int) $b[$j]);
                $result[$position] = $sum % 10;
                $result[$position - 1] += intdiv($sum, 10);
            }
        }

        $text = ltrim(implode('', $result), '0');
        return $text === '' ? '0' : $text;
    }

    private function addUnsigned(string $a, string $b): string
    {
        $width = max(strlen($a), strlen($b));
        $a = str_pad($a, $width, '0', STR_PAD_LEFT);
        $b = str_pad($b, $width, '0', STR_PAD_LEFT);
        $carry = 0;
        $result = '';
        for ($i = $width - 1; $i >= 0; $i--) {
            $sum = (int) $a[$i] + (int) $b[$i] + $carry;
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }
        if ($carry) {
            $result = (string) $carry . $result;
        }
        return ltrim($result, '0') ?: '0';
    }

    private function subtractUnsigned(string $larger, string $smaller): string
    {
        $width = max(strlen($larger), strlen($smaller));
        $larger = str_pad($larger, $width, '0', STR_PAD_LEFT);
        $smaller = str_pad($smaller, $width, '0', STR_PAD_LEFT);
        $borrow = 0;
        $result = '';
        for ($i = $width - 1; $i >= 0; $i--) {
            $digit = (int) $larger[$i] - $borrow - (int) $smaller[$i];
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = (string) $digit . $result;
        }
        return ltrim($result, '0') ?: '0';
    }

    private function compareUnsigned(string $a, string $b): int
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        if (strlen($a) !== strlen($b)) {
            return strlen($a) <=> strlen($b);
        }
        return strcmp($a, $b) <=> 0;
    }

    private function divideUnsigned(string $numerator, string $denominator): string
    {
        $numerator = ltrim($numerator, '0') ?: '0';
        $denominator = ltrim($denominator, '0') ?: '0';
        if ($denominator === '0') {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }
        if ($this->compareUnsigned($numerator, $denominator) < 0) {
            return '0';
        }

        $quotient = '';
        $remainder = '0';
        foreach (str_split($numerator) as $digit) {
            $remainder = ltrim(($remainder === '0' ? '' : $remainder) . $digit, '0') ?: '0';
            $qDigit = 0;
            while ($this->compareUnsigned($remainder, $denominator) >= 0) {
                $remainder = $this->subtractUnsigned($remainder, $denominator);
                $qDigit++;
            }
            $quotient .= (string) $qDigit;
        }

        return ltrim($quotient, '0') ?: '0';
    }

    private function addUnsignedOne(string $digits): string
    {
        $chars = str_split($digits === '' ? '0' : $digits);
        $carry = 1;
        for ($i = count($chars) - 1; $i >= 0 && $carry; $i--) {
            $value = (int) $chars[$i] + $carry;
            $chars[$i] = (string) ($value % 10);
            $carry = intdiv($value, 10);
        }
        if ($carry) {
            array_unshift($chars, '1');
        }
        return implode('', $chars);
    }
}
