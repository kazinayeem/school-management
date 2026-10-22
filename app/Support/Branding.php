<?php

namespace App\Support;

class Branding
{
    public const IMAGES = ['brand_logo' => 'Logo', 'brand_logo_dark' => 'Logo for dark backgrounds', 'pdf_banner' => 'PDF letterhead banner'];

    public const DEFAULT_PRIMARY = '#b31299';
    public const DEFAULT_SECONDARY = '#7c3aed';

    /** The two colours the administrator picked, as #rrggbb. */
    public static function colors(): array
    {
        $ok = fn (?string $c, string $d) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : $d;

        return [
            'primary' => $ok(SiteSettings::get('brand_primary'), self::DEFAULT_PRIMARY),
            'secondary' => $ok(SiteSettings::get('brand_secondary'), self::DEFAULT_SECONDARY),
        ];
    }

    /** Mix a colour towards white (t > 0) or black (t < 0). */
    public static function mix(string $hex, float $t): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $to = $t >= 0 ? 255 : 0;
        $t = abs($t);
        $f = fn (int $c) => (int) round($c + ($to - $c) * $t);

        return sprintf('#%02x%02x%02x', $f($r), $f($g), $f($b));
    }

    /** Light-to-dark steps of one colour, the way the interface names them (50 lightest, 950 darkest). The colour itself is step 700. */
    public static function scale(string $hex): array
    {
        return [50 => self::mix($hex, 0.94), 100 => self::mix($hex, 0.87), 200 => self::mix($hex, 0.74), 300 => self::mix($hex, 0.56), 400 => self::mix($hex, 0.36), 500 => self::mix($hex, 0.18), 600 => self::mix($hex, 0.08),
            700 => $hex, 800 => self::mix($hex, -0.18), 900 => self::mix($hex, -0.34), 950 => self::mix($hex, -0.5)];
    }

    /** The :root rule that recolours the whole interface. */
    public static function css(): string
    {
        ['primary' => $p, 'secondary' => $s] = self::colors();
        $ps = self::scale($p);
        $ss = self::scale($s);
        $vars = ["--brand-primary:{$p}", '--brand-primary-dark:'.$ps[800], '--brand-primary-light:'.$ps[500], '--brand-primary-soft:'.$ps[50],
            "--brand-secondary:{$s}", '--brand-secondary-dark:'.$ss[800], '--brand-secondary-soft:'.$ss[50]];
        foreach (['fuchsia', 'indigo', 'pink'] as $name) {
            foreach ($ps as $step => $c) {
                $vars[] = "--color-{$name}-{$step}:{$c}";
            }
        }
        foreach (['violet', 'purple'] as $name) {
            foreach ($ss as $step => $c) {
                $vars[] = "--color-{$name}-{$step}:{$c}";
            }
        }

        return ':root{'.implode(';', $vars).'}';
    }

    /** The school's name as the administrator set it, falling back to the app name. */
    public static function name(): string
    {
        return trim((string) SiteSettings::get('school_name', '')) ?: (string) config('app.name', 'ReadySchool');
    }

    /** URL of an uploaded branding picture, or the built-in default (or null for the banner). */
    public static function image(string $key, ?string $default = null): ?string
    {
        $path = SiteSettings::get($key, '');

        return $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path) ? Photos::url($path) : $default;
    }

    public static function data(): array
    {
        $logo = self::image('brand_logo', asset('images/logo.png'));

        return [
            'name' => self::name(),
            'logo' => $logo,
            'logoWhite' => self::image('brand_logo_dark', self::image('brand_logo') ?: asset('images/logo-white.png')),
            'favicon' => asset('favicon.ico'),
            'theme' => self::colors(),
            'demo' => [
                'admin' => [
                    'username' => 'superadmin',
                    'password' => 'Demo@1234',
                    'email' => 'superadmin@sms.local',
                    'name' => 'Super Admin',
                    'role' => 'Super Admin',
                ],
                'teacher' => [
                    'username' => 'jsmith',
                    'password' => 'Demo@1234',
                    'email' => 'teacher@sms.local',
                    'name' => 'Md. Jahangir Alam',
                    'role' => 'Teacher',
                ],
                'staff' => [
                    'username' => 'accountant',
                    'password' => 'Demo@1234',
                    'email' => 'staff@sms.local',
                    'name' => 'Shafiqul Islam',
                    'role' => 'Staff',
                ],
                'student' => [
                    'username' => 'student',
                    'password' => 'Demo@1234',
                    'email' => 'student@sms.local',
                    'name' => 'Tanvir Ahmed',
                    'role' => 'Student',
                ],
                'parent' => [
                    'username' => 'parent',
                    'password' => 'Demo@1234',
                    'email' => 'parent@sms.local',
                    'name' => 'Md. Kamal Uddin Ahmed',
                    'role' => 'Parent',
                ],
            ],
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧', 'is_rtl' => false, 'is_default' => true],
                ['code' => 'ar', 'name' => 'العربية', 'flag' => '🇸🇦', 'is_rtl' => true, 'is_default' => false],
                ['code' => 'bn', 'name' => 'বাংলা', 'flag' => '🇧🇩', 'is_rtl' => false, 'is_default' => false],
            ],
        ];
    }
}
