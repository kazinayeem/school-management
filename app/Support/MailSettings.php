<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

class MailSettings
{
    /**
     * Point the mailer at the SMTP server saved under Settings → Email / SMTP.
     * The password is never stored in the database; it stays in MAIL_PASSWORD.
     */
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('site_settings')) {
                return;
            }

            $host = trim((string) SiteSettings::get('mail_host', ''));
            if ($host === '') {
                return;
            }

            $overrides = [
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => SiteSettings::int('mail_port', 587) ?: 587,
                'mail.mailers.smtp.scheme' => SiteSettings::get('mail_encryption', 'tls') === 'ssl' ? 'smtps' : 'smtp',
            ];
            if (filled($username = SiteSettings::get('mail_username'))) {
                $overrides['mail.mailers.smtp.username'] = $username;
            }
            if (filled($from = SiteSettings::get('mail_from_address'))) {
                $overrides['mail.from.address'] = $from;
            }
            if (filled($name = SiteSettings::get('mail_from_name'))) {
                $overrides['mail.from.name'] = $name;
            }

            config($overrides);
        } catch (Throwable) {
            // The database may not be ready yet (for example while migrating).
        }
    }
}
