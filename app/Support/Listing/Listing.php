<?php

namespace App\Support\Listing;

use Illuminate\Http\Request;

class Listing
{
    public const LIMITS = [10, 20, 50];
    public const DEFAULT_LIMIT = 20;

    public static function limit(Request $request): int
    {
        $limit = (int) ($request->input('limit') ?? $request->input('per_page') ?? self::DEFAULT_LIMIT);

        return in_array($limit, self::LIMITS, true) ? $limit : self::DEFAULT_LIMIT;
    }

    /**
     * One page of a dataset, counted and sliced in the database.
     *
     * @return array{rows: \Illuminate\Support\Collection, pagination: array<string, int|string>}
     */
    public static function page(Dataset $dataset, Request $request): array
    {
        $limit = self::limit($request);
        $query = $dataset->query($request);
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, (int) $request->input('page', 1)), $pages);

        $rows = $dataset->sort($query, $request)->forPage($page, $limit)->get();

        return ['rows' => $rows, 'pagination' => [
            'page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => $pages,
            'from' => $total === 0 ? 0 : ($page - 1) * $limit + 1, 'to' => min($page * $limit, $total),
            'sort' => array_key_exists((string) $request->input('sort'), $dataset->sorts()) ? (string) $request->input('sort') : array_key_first($dataset->sorts()),
            'dir' => strtolower((string) $request->input('dir', $dataset->defaultDir())) === 'desc' ? 'desc' : 'asc',
        ]];
    }
}
