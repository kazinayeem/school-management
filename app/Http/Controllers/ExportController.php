<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Listing\Dataset;
use App\Support\Listing\Datasets;
use App\Support\Listing\Listing;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/** Export or print a list: this page, the rows someone ticked, or every row that matches the filters. */
class ExportController extends Controller
{
    /** PDF and print build the whole table in memory, so they stop here. CSV and Excel stream and have no such limit. */
    private const DOCUMENT_ROWS = 2000;

    public function __invoke(Request $request, string $key)
    {
        $dataset = Datasets::find($key);
        abort_if($dataset === null || ! $dataset->authorize($request), 404);

        $data = $request->validate([
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf', 'html'])],
            'scope' => ['required', Rule::in(['page', 'selected', 'all'])],
            'ids' => ['nullable', 'array', 'max:20000'],
            'ids.*' => ['integer'],
            'exclude' => ['nullable', 'array', 'max:20000'],
            'exclude.*' => ['integer'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = $dataset->columns();
        if (! empty($data['columns'])) {
            $columns = array_intersect_key($columns, array_flip($data['columns']));
        }
        abort_if($columns === [], 422, 'Choose at least one column.');

        $query = $this->scoped($dataset, $request, $data);
        $count = (clone $query)->count();
        $query = $dataset->sort($query, $request);
        if ($data['scope'] === 'page') {
            $limit = Listing::limit($request);
            $page = max(1, (int) $request->input('page', 1));
            $query->forPage($page, $limit);
            $count = max(0, min($limit, $count - ($page - 1) * $limit));
        }

        abort_if($count === 0, 422, 'There is nothing to export.');
        if (in_array($data['format'], ['pdf', 'html'], true) && $count > self::DOCUMENT_ROWS) {
            return response()->json(['message' => "A PDF or printout can hold up to ".self::DOCUMENT_ROWS." rows. This has {$count}. Use Excel or CSV for the whole set, or narrow the filters."], 422);
        }

        Audit::log('other', 'Export', "{$dataset->title()} · {$data['format']} · {$data['scope']} · {$count} rows");
        $base = str($dataset->key())->slug().'-'.now()->format('Ymd-His');

        return match ($data['format']) {
            'csv' => $this->csv($dataset, $query, $columns, "{$base}.csv"),
            'xlsx' => $this->xlsx($dataset, $query, $columns, "{$base}.xlsx"),
            default => $this->document($dataset, $request, $query, $columns, $count, $data['format'], "{$base}.pdf"),
        };
    }

    /** The authorised, filtered rows; ticked ids and exclusions are applied on top. */
    private function scoped(Dataset $dataset, Request $request, array $data): \Illuminate\Database\Query\Builder
    {
        if ($data['scope'] === 'selected') {
            // A tick may be older than the filters now on screen, so only permissions apply here.
            $clean = $request->duplicate([], []);
            $clean->setUserResolver($request->getUserResolver());

            return $dataset->query($clean)->whereIn($dataset->idColumn(), $data['ids'] ?? [0]);
        }
        $query = $dataset->query($request);
        if ($data['scope'] === 'all' && ! empty($data['exclude'])) {
            $query->whereNotIn($dataset->idColumn(), $data['exclude']);
        }

        return $query;
    }

    /** Stops a cell from being run as a formula when the file is opened in a spreadsheet. */
    private static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    private function csv(Dataset $dataset, $query, array $columns, string $filename)
    {
        return response()->streamDownload(function () use ($dataset, $query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));
            foreach ($query->cursor() as $row) {
                $mapped = $dataset->map($row);
                fputcsv($out, array_map(fn ($k) => self::safe($mapped[$k] ?? ''), array_keys($columns)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function xlsx(Dataset $dataset, $query, array $columns, string $filename)
    {
        return response()->streamDownload(function () use ($dataset, $query, $columns) {
            $writer = new XlsxWriter;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValuesWithStyle(array_values($columns), (new Style)->withFontBold(true)));
            foreach ($query->cursor() as $row) {
                $mapped = $dataset->map($row);
                $writer->addRow(Row::fromValues(array_map(fn ($k) => self::safe($mapped[$k] ?? ''), array_keys($columns))));
            }
            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function document(Dataset $dataset, Request $request, $query, array $columns, int $count, string $format, string $filename)
    {
        $rows = [];
        foreach ($query->cursor() as $row) {
            $mapped = $dataset->map($row);
            $rows[] = collect($columns)->mapWithKeys(fn ($label, $k) => [$k => $mapped[$k] ?? ''])->all();
        }
        $data = [
            'title' => $dataset->title(), 'subtitle' => "{$count} record".($count === 1 ? '' : 's'), 'filters' => $dataset->describe($request),
            'sections' => [['title' => null, 'columns' => collect($columns)->map(fn ($label, $k) => ['key' => $k, 'label' => $label])->values()->all(), 'rows' => $rows]],
        ];

        if ($format === 'html') {
            return response(view('pdf.table', $data + ['school' => Pdf::school()])->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return Pdf::download('pdf.table', $data, $filename, 'a4', count($columns) > 6 ? 'landscape' : 'portrait');
    }
}
