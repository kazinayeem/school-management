<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/** Builds real PDF files on the server so a download works in any browser, with no print dialog. */
class Pdf
{
    private const PATTERNS = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];

    /** Render a Blade view to a PDF download. */
    public static function download(string $view, array $data, string $filename, string $paper = 'a4', string $orientation = 'portrait'): Response
    {
        return response(self::render($view, $data, $paper, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function render(string $view, array $data, string $paper = 'a4', string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [base_path(), storage_path()]);
        $options->set('tempDir', storage_path('app/pdf-tmp'));
        $options->set('fontCache', storage_path('app/pdf-tmp'));
        @mkdir(storage_path('app/pdf-tmp'), 0775, true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data + ['school' => self::school()])->render());
        $pdf->setPaper($paper, $orientation);
        $pdf->render();

        return (string) $pdf->output();
    }

    /** @return array{name: string, address: string, phone: string, logo: ?string} */
    public static function school(): array
    {
        return [
            'name' => Branding::name(), 'address' => SiteSettings::get('address', '') ?? '', 'phone' => SiteSettings::get('phone', '') ?? '',
            'logo' => self::brandUri('brand_logo') ?? self::fileUri(public_path('images/logo.png')),
            'banner' => self::brandUri('pdf_banner'), 'color' => Branding::colors()['primary'],
        ];
    }

    /** A picture stored on the public disk (photos/...) or a file path as an inline data URI, or null. */
    public static function photo(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return self::fileUri(Storage::disk('public')->path($path));
    }

    private static function brandUri(string $key): ?string
    {
        $path = SiteSettings::get($key, '');

        return $path ? self::photo($path) : null;
    }

    public static function fileUri(string $file): ?string
    {
        if (! is_file($file)) {
            return null;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }

    /** Code 128 (set B) as an inline SVG picture. */
    public static function barcode(string $text, int $height = 30): string
    {
        $values = [104];
        foreach (str_split($text) as $ch) {
            $c = ord($ch);
            $values[] = $c >= 32 && $c <= 126 ? $c - 32 : 0;
        }
        $sum = 0;
        foreach ($values as $i => $v) {
            $sum += $v * ($i === 0 ? 1 : $i);
        }
        $values[] = $sum % 103;
        $values[] = 106;

        $x = 10;
        $rects = '';
        foreach ($values as $v) {
            foreach (str_split(self::PATTERNS[$v]) as $i => $digit) {
                $w = (int) $digit;
                if ($i % 2 === 0) {
                    $rects .= "<rect x=\"{$x}\" y=\"0\" width=\"{$w}\" height=\"{$height}\" fill=\"#111\"/>";
                }
                $x += $w;
            }
        }
        $width = $x + 10;
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\"><rect width=\"{$width}\" height=\"{$height}\" fill=\"#fff\"/>{$rects}</svg>";

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
