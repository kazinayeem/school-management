<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsFormController extends Controller
{
    /** @return array<string, array<string, mixed>> */
    private function forms(): array
    {
        return [
            'gateway' => [
                'path' => '/app/fees/gateway',
                'label' => 'Payment Gateway',
                'subtitle' => 'How families pay fees online. Secret keys belong in the server .env file, not here.',
                'fields' => [
                    ['name' => 'gateway_enabled', 'label' => 'Accept online payments', 'type' => 'checkbox', 'default' => false],
                    ['name' => 'gateway_provider', 'label' => 'Provider', 'type' => 'select', 'rules' => 'required|in:bKash,Nagad,SSLCommerz,Stripe,Manual', 'default' => 'Manual', 'options' => array_map(fn ($p) => ['value' => $p, 'label' => $p], ['bKash', 'Nagad', 'SSLCommerz', 'Stripe', 'Manual'])],
                    ['name' => 'gateway_merchant_id', 'label' => 'Merchant / store ID', 'type' => 'text', 'rules' => 'nullable|string|max:100', 'default' => ''],
                    ['name' => 'gateway_mode', 'label' => 'Mode', 'type' => 'select', 'rules' => 'required|in:sandbox,live', 'default' => 'sandbox', 'options' => [['value' => 'sandbox', 'label' => 'Sandbox (testing)'], ['value' => 'live', 'label' => 'Live']]],
                    ['name' => 'gateway_instructions', 'label' => 'Instructions shown to parents', 'type' => 'textarea', 'rules' => 'nullable|string|max:1000', 'default' => ''],
                ],
            ],
            'general' => [
                'path' => '/app/settings/general',
                'label' => 'General',
                'subtitle' => 'Basic details about the school.',
                'fields' => [
                    ['name' => 'school_name', 'label' => 'School name', 'type' => 'text', 'rules' => 'required|string|max:150', 'default' => 'Borno School'],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => ''],
                    ['name' => 'email', 'label' => 'Contact email', 'type' => 'text', 'rules' => 'nullable|email|max:150', 'default' => ''],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'rules' => 'nullable|string|max:500', 'default' => ''],
                    ['name' => 'currency', 'label' => 'Currency symbol', 'type' => 'text', 'rules' => 'required|string|max:6', 'default' => 'Tk'],
                    ['name' => 'timezone', 'label' => 'Time zone', 'type' => 'select', 'rules' => 'required|in:Asia/Dhaka,Asia/Riyadh,UTC', 'default' => 'Asia/Dhaka', 'options' => [['value' => 'Asia/Dhaka', 'label' => 'Asia/Dhaka'], ['value' => 'Asia/Riyadh', 'label' => 'Asia/Riyadh'], ['value' => 'UTC', 'label' => 'UTC']]],
                ],
            ],
            'attendance' => [
                'path' => '/app/settings/attendance',
                'label' => 'Attendance Rules',
                'subtitle' => 'When a student counts as late, and the attendance they need.',
                'fields' => [
                    ['name' => 'attendance_late_after', 'label' => 'Late after', 'type' => 'time', 'rules' => 'required|date_format:H:i', 'default' => '08:15'],
                    ['name' => 'attendance_min_percent', 'label' => 'Minimum attendance (%)', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100', 'default' => '75'],
                    ['name' => 'attendance_notify_absent', 'label' => 'Tell guardians when a student is absent', 'type' => 'checkbox', 'default' => false],
                ],
            ],
            'fees' => [
                'path' => '/app/settings/fees',
                'label' => 'Fees & Late Fee',
                'subtitle' => 'The late fee added to invoices that are paid after their due date.',
                'fields' => [
                    ['name' => 'late_fee_enabled', 'label' => 'Charge a late fee', 'type' => 'checkbox', 'default' => false],
                    ['name' => 'late_fee_kind', 'label' => 'Late fee type', 'type' => 'select', 'rules' => 'required|in:fixed,percent', 'default' => 'fixed', 'options' => [['value' => 'fixed', 'label' => 'Fixed amount'], ['value' => 'percent', 'label' => 'Percent']]],
                    ['name' => 'late_fee_value', 'label' => 'Late fee value', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100000', 'default' => '0'],
                    ['name' => 'late_fee_grace_days', 'label' => 'Grace days', 'type' => 'number', 'rules' => 'required|integer|min:0|max:365', 'default' => '0'],
                ],
            ],
            'smsgateway' => [
                'path' => '/app/sms/settings',
                'label' => 'SMS Gateway',
                'subtitle' => 'The service used to deliver text messages. API keys belong in the server .env file.',
                'fields' => [
                    ['name' => 'sms_enabled', 'label' => 'Enable sending', 'type' => 'checkbox', 'default' => false],
                    ['name' => 'sms_provider', 'label' => 'Provider', 'type' => 'select', 'rules' => 'required|in:manual,bulksmsbd,twilio', 'default' => 'manual', 'options' => [['value' => 'manual', 'label' => 'Manual (queue only)'], ['value' => 'bulksmsbd', 'label' => 'BulkSMSBD'], ['value' => 'twilio', 'label' => 'Twilio']]],
                    ['name' => 'sms_sender_id', 'label' => 'Sender ID', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'default' => ''],
                ],
            ],
            'mail' => [
                'path' => '/app/settings/mail',
                'label' => 'Email / SMTP',
                'subtitle' => 'Outgoing email details. The SMTP password belongs in the server .env file.',
                'test' => ['url' => '/app/settings/mail/test', 'label' => 'Send a test email'],
                'fields' => [
                    ['name' => 'mail_host', 'label' => 'SMTP host', 'type' => 'text', 'rules' => 'nullable|string|max:150', 'default' => ''],
                    ['name' => 'mail_port', 'label' => 'Port', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:65535', 'default' => '587'],
                    ['name' => 'mail_username', 'label' => 'Username', 'type' => 'text', 'rules' => 'nullable|string|max:150', 'default' => ''],
                    ['name' => 'mail_encryption', 'label' => 'Encryption', 'type' => 'select', 'rules' => 'required|in:tls,ssl,none', 'default' => 'tls', 'options' => [['value' => 'tls', 'label' => 'TLS'], ['value' => 'ssl', 'label' => 'SSL'], ['value' => 'none', 'label' => 'None']]],
                    ['name' => 'mail_from_address', 'label' => 'From address', 'type' => 'text', 'rules' => 'nullable|email|max:150', 'default' => ''],
                    ['name' => 'mail_from_name', 'label' => 'From name', 'type' => 'text', 'rules' => 'nullable|string|max:100', 'default' => ''],
                ],
            ],
            'appearance' => [
                'path' => '/app/settings/appearance',
                'label' => 'Appearance',
                'subtitle' => 'The theme new visitors see before they choose their own.',
                'fields' => [
                    ['name' => 'default_theme', 'label' => 'Default theme', 'type' => 'select', 'rules' => 'required|in:system,light,dark', 'default' => 'system', 'options' => [['value' => 'system', 'label' => 'Follow the device'], ['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']]],
                ],
            ],
            'languages' => [
                'path' => '/app/settings/languages',
                'label' => 'Languages',
                'subtitle' => 'Which languages visitors can choose from, and the one they start with.',
                'fields' => [
                    ['name' => 'lang_en', 'label' => 'English', 'type' => 'checkbox', 'default' => true],
                    ['name' => 'lang_bn', 'label' => 'বাংলা', 'type' => 'checkbox', 'default' => true],
                    ['name' => 'lang_ar', 'label' => 'العربية', 'type' => 'checkbox', 'default' => true],
                    ['name' => 'default_language', 'label' => 'Default language', 'type' => 'select', 'rules' => 'required|in:en,bn,ar', 'default' => 'en', 'options' => [['value' => 'en', 'label' => 'English'], ['value' => 'bn', 'label' => 'বাংলা'], ['value' => 'ar', 'label' => 'العربية']]],
                ],
            ],
            'library' => [
                'path' => '/app/library/settings',
                'label' => 'Library Settings',
                'subtitle' => 'Loan period, late fine and how many books a member can hold.',
                'fields' => [
                    ['name' => 'library_loan_days', 'label' => 'Loan period (days)', 'type' => 'number', 'rules' => 'required|integer|min:1|max:365', 'default' => '14'],
                    ['name' => 'library_fine_per_day', 'label' => 'Fine per late day', 'type' => 'number', 'rules' => 'required|integer|min:0|max:10000', 'default' => '5'],
                    ['name' => 'library_max_books', 'label' => 'Books per member', 'type' => 'number', 'rules' => 'required|integer|min:1|max:50', 'default' => '3'],
                ],
            ],
        ];
    }

    public function show(Request $request, string $form): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        $config = $this->forms()[$form];
        $stored = DB::table('site_settings')->pluck('value', 'key');

        $values = [];
        foreach ($config['fields'] as $f) {
            $raw = $stored[$f['name']] ?? null;
            $values[$f['name']] = $f['type'] === 'checkbox' ? ($raw === null ? $f['default'] : (bool) $raw) : ($raw ?? $f['default']);
        }

        return Inertia::render('office/website', [
            'navigation' => OfficeMenu::for('admin'),
            'roleLabel' => 'Super Admin',
            'resource' => 'settings',
            'baseUrl' => $config['path'],
            'definition' => ['label' => $config['label'], 'subtitle' => $config['subtitle'], 'fields' => $config['fields'], 'test' => $config['test'] ?? null],
            'values' => $values,
        ]);
    }

    public function save(Request $request, string $form): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $fields = $this->forms()[$form]['fields'];
        $data = $request->validate(collect($fields)->mapWithKeys(fn ($f) => [$f['name'] => $f['type'] === 'checkbox' ? 'boolean' : $f['rules']])->all());

        if ($form === 'languages') {
            $enabled = array_filter(['en', 'bn', 'ar'], fn ($c) => ! empty($data["lang_{$c}"]));
            if ($enabled === []) {
                return back()->withErrors(['lang_en' => 'Keep at least one language turned on.']);
            }
            if (! in_array($data['default_language'], $enabled, true)) {
                return back()->withErrors(['default_language' => 'The default language must be one of the enabled languages.']);
            }
        }

        foreach ($fields as $f) {
            $value = $f['type'] === 'checkbox' ? (int) ($data[$f['name']] ?? 0) : ($data[$f['name']] ?? '');
            DB::table('site_settings')->updateOrInsert(['key' => $f['name']], ['value' => (string) $value]);
        }
        \App\Support\Audit::log('updated', $this->forms()[$form]['label'].' settings');

        return back()->with('status', 'Settings saved.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);

        if (blank(\App\Support\SiteSettings::get('mail_host'))) {
            return back()->with('error', 'Save an SMTP host first. Until then email is only written to the log.');
        }

        try {
            \Illuminate\Support\Facades\Mail::raw('This is a test email from '.(\App\Support\SiteSettings::get('school_name') ?: config('app.name')).'. Your email settings work.', fn ($m) => $m->to($data['email'])->subject('Test email'));
        } catch (\Throwable $e) {
            return back()->with('error', 'The test email could not be sent: '.mb_substr($e->getMessage(), 0, 200));
        }
        \App\Support\Audit::log('other', 'Email settings', 'Test email sent');

        return back()->with('status', 'Test email sent.');
    }
}
