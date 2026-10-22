<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Branding;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The school's name, logo and PDF letterhead. Changing them changes the website, the login page, every PDF and every printed card. */
class BrandingController extends Controller
{
    private const WIDTHS = ['brand_logo' => 600, 'brand_logo_dark' => 600, 'pdf_banner' => 1600];

    public function show(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/branding', [
            'navigation' => OfficeMenu::for('admin'),
            'roleLabel' => Permissions::label('admin'),
            'name' => Branding::name(),
            'tagline' => SiteSettings::get('tagline', ''),
            'colors' => Branding::colors(),
            'defaultColors' => ['primary' => Branding::DEFAULT_PRIMARY, 'secondary' => Branding::DEFAULT_SECONDARY],
            'images' => collect(Branding::IMAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'url' => Branding::image($key), 'custom' => (bool) SiteSettings::get($key, '')])->values()->all(),
            'defaults' => ['logo' => asset('images/logo.png')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'brand_primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_secondary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'brand_logo_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'pdf_banner' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['in:brand_logo,brand_logo_dark,pdf_banner'],
        ]);

        DB::table('site_settings')->updateOrInsert(['key' => 'school_name'], ['value' => $data['school_name']]);
        DB::table('site_settings')->updateOrInsert(['key' => 'tagline'], ['value' => $data['tagline'] ?? '']);

        foreach (['brand_primary', 'brand_secondary'] as $key) {
            if (filled($data[$key] ?? null)) {
                DB::table('site_settings')->updateOrInsert(['key' => $key], ['value' => strtolower($data[$key])]);
            }
        }

        foreach (array_keys(Branding::IMAGES) as $key) {
            $old = SiteSettings::get($key, '');
            if ($request->hasFile($key)) {
                $path = $this->store($request->file($key), $key);
                DB::table('site_settings')->updateOrInsert(['key' => $key], ['value' => $path]);
                $old && Storage::disk('public')->delete($old);
            } elseif (in_array($key, $data['remove'] ?? [], true) && $old) {
                Storage::disk('public')->delete($old);
                DB::table('site_settings')->where('key', $key)->delete();
            }
        }
        Audit::log('updated', 'Branding', $data['school_name']);

        return back()->with('status', 'Branding saved. The website, login page and every PDF now use it.');
    }

    /** Decode, scale down and re-save, so what is served is always a clean PNG or JPEG. */
    private function store(UploadedFile $file, string $key): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($src === false) {
            throw ValidationException::withMessages([$key => 'That file could not be read as a picture.']);
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::WIDTHS[$key] / $w);
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $canvas = imagecreatetruecolor($nw, $nh);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagepng($canvas, null, 6);
        $bytes = (string) ob_get_clean();
        $path = 'branding/'.$key.'-'.Str::random(8).'.png';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
