<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Photos
{
    public const MAX_EDGE = 600;

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('public');
    }

    public static function url(?string $path): ?string
    {
        return $path ? self::disk()->url($path) : null;
    }

    /**
     * Save an uploaded picture. It is decoded, scaled down to at most 600 px on its
     * longest side and re-encoded as a JPEG, so whatever was uploaded (including
     * hidden metadata) is never served back as it came in.
     */
    public static function store(UploadedFile $file, string $folder): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'That file could not be read as a picture.']);
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 85);
        $bytes = (string) ob_get_clean();

        $path = "photos/{$folder}/".Str::uuid().'.jpg';
        self::disk()->put($path, $bytes);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'photos/')) {
            self::disk()->delete($path);
        }
    }
}
