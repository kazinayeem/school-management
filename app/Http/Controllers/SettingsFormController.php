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
            'definition' => ['label' => $config['label'], 'subtitle' => $config['subtitle'], 'fields' => $config['fields']],
            'values' => $values,
        ]);
    }

    public function save(Request $request, string $form): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $fields = $this->forms()[$form]['fields'];
        $data = $request->validate(collect($fields)->mapWithKeys(fn ($f) => [$f['name'] => $f['type'] === 'checkbox' ? 'boolean' : $f['rules']])->all());

        foreach ($fields as $f) {
            $value = $f['type'] === 'checkbox' ? (int) ($data[$f['name']] ?? 0) : ($data[$f['name']] ?? '');
            DB::table('site_settings')->updateOrInsert(['key' => $f['name']], ['value' => (string) $value]);
        }

        return back()->with('status', 'Settings saved.');
    }
}
