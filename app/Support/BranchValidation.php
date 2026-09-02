<?php

namespace App\Support;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Rule;

class BranchValidation
{
    public static function branchId(?int $branchId = null): int
    {
        return $branchId ?: app(BranchContext::class)->requireSpecificBranch();
    }

    public static function exists(string $table, string $column = 'id', ?int $branchId = null): Exists
    {
        $branchId = self::branchId($branchId);
        return Rule::exists($table, $column)->where(fn ($query) => $query->where('branch_id', $branchId));
    }

    public static function unique(string $table, string $column, ?int $ignoreId = null, ?int $branchId = null): Unique
    {
        $branchId = self::branchId($branchId);
        $rule = Rule::unique($table, $column)->where(fn ($query) => $query->where('branch_id', $branchId));
        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
    }
}
