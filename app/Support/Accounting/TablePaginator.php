<?php

namespace App\Support\Accounting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * AJAX-friendly pagination for existing small accounting reference/register lists.
 * For large transaction tables, controllers should continue to paginate SQL queries.
 * This never changes a collection used elsewhere by create/edit forms.
 */
final class TablePaginator
{
    public static function paginate(iterable $rows, string $pageName, int $perPage = 20): PaginatorContract
    {
        if ($rows instanceof PaginatorContract) {
            return $rows;
        }

        $collection = $rows instanceof Collection ? $rows : collect($rows);
        $perPage = max(1, min($perPage, 100));
        if (request()->boolean('print_all')) {
            $perPage = max(1, $collection->count());
        }
        $currentPage = max(1, (int) request()->query($pageName, 1));
        return (new LengthAwarePaginator(
            $collection->slice(($currentPage - 1) * $perPage, $perPage)->values(),
            $collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => $pageName]
        ))->appends(request()->except($pageName));
    }
}
