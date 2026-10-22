<?php

namespace App\Support\Listing;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * One list the school can page through, select from and export. The same query serves the
 * on-screen page and every export, so filters, permissions and counts can never disagree.
 */
abstract class Dataset
{
    abstract public function key(): string;

    abstract public function title(): string;

    /** @return array<string, string> export column key => heading */
    abstract public function columns(): array;

    /** Filtered, permission-checked rows. Must expose the primary key as `id`. */
    abstract public function query(Request $request): Builder;

    /** One row as plain values, keyed like columns(). */
    abstract public function map(object $row): array;

    /** @return array<string, string> sortable field => SQL column. The first is the default. */
    abstract public function sorts(): array;

    abstract public function authorize(Request $request): bool;

    /** The filters that were applied, as a short sentence for exports. */
    public function describe(Request $request): string
    {
        return '';
    }

    /** Orders a query by the requested field, falling back to the default, always finishing on the id for a stable order. */
    public function sort(Builder $query, Request $request): Builder
    {
        $sorts = $this->sorts();
        $field = array_key_exists((string) $request->input('sort'), $sorts) ? (string) $request->input('sort') : array_key_first($sorts);
        $dir = strtolower((string) $request->input('dir', $this->defaultDir())) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sorts[$field], $dir)->orderBy($this->idColumn());
    }

    public function defaultDir(): string
    {
        return 'asc';
    }

    public function idColumn(): string
    {
        return 'users.id';
    }

    /** @return array<string, string> */
    public function filterLabels(): array
    {
        return [];
    }
}
