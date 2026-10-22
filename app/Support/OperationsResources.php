<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class OperationsResources
{
    /** @return list<array{value: string, label: string}> */
    private static function classOptions(string $blank): array
    {
        return [['value' => '', 'label' => $blank], ...array_map(fn ($n) => ['value' => "Class {$n}", 'label' => "Class {$n}"], range(1, 10))];
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function groups(): array
    {
        return [
            'fees' => [
                'structure' => [
                    'label' => 'Fee Structure',
                    'subtitle' => 'Fee heads and their amounts. Use them when generating invoices.',
                    'table' => 'fee_heads',
                    'order' => ['name', 'asc'],
                    'columns' => ['name', 'class_name', 'amount', 'active'],
                    'column_labels' => ['class_name' => 'Class'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Fee name', 'type' => 'text', 'rules' => 'required|string|max:100'],
                        ['name' => 'class_name', 'label' => 'Class', 'type' => 'select', 'options' => self::classOptions('All classes'), 'rules' => 'nullable|string|max:20', 'hint' => 'Leave on "All classes" to charge every class.'],
                        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'rules' => 'required|integer|min:1', 'default' => 0],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'accounting' => [
                'chart' => [
                    'label' => 'Chart of Accounts',
                    'subtitle' => 'The accounts used in vouchers and reports.',
                    'table' => 'accounts',
                    'order' => ['code', 'asc'],
                    'columns' => ['code', 'name', 'type', 'active'],
                    'delete_guard' => ['voucher_lines', 'account_id', 'This account has vouchers and cannot be deleted.'],
                    'fields' => [
                        ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:20|unique:accounts,code'],
                        ['name' => 'name', 'label' => 'Account name', 'type' => 'text', 'rules' => 'required|string|max:150'],
                        ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'rules' => 'required|in:asset,liability,equity,income,expense', 'options' => [
                            ['value' => 'asset', 'label' => 'Asset'], ['value' => 'liability', 'label' => 'Liability'], ['value' => 'equity', 'label' => 'Equity'],
                            ['value' => 'income', 'label' => 'Income'], ['value' => 'expense', 'label' => 'Expense'],
                        ]],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'devices' => [
                'devices' => [
                    'label' => 'Punch Devices',
                    'subtitle' => 'Attendance terminals such as fingerprint and card readers.',
                    'table' => 'devices',
                    'base' => '/app/devices',
                    'order' => ['name', 'asc'],
                    'columns' => ['name', 'kind', 'location', 'serial', 'active'],
                    'column_labels' => ['kind' => 'Type', 'serial' => 'Serial no.'],
                    'delete_guard' => ['punches', 'device_id', 'This device has punch records. Mark it inactive instead.'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Device name', 'type' => 'text', 'rules' => 'required|string|max:100'],
                        ['name' => 'kind', 'label' => 'Type', 'type' => 'select', 'rules' => 'required|in:fingerprint,card,face', 'options' => [['value' => 'fingerprint', 'label' => 'Fingerprint'], ['value' => 'card', 'label' => 'Card reader'], ['value' => 'face', 'label' => 'Face']]],
                        ['name' => 'location', 'label' => 'Location', 'type' => 'text', 'rules' => 'nullable|string|max:150'],
                        ['name' => 'serial', 'label' => 'Serial no.', 'type' => 'text', 'rules' => 'nullable|string|max:100|unique:devices,serial'],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
                'people' => [
                    'label' => 'Cards & Fingerprints',
                    'subtitle' => 'Which card or fingerprint ID belongs to which person.',
                    'table' => 'device_credentials',
                    'order' => ['id', 'desc'],
                    'columns' => ['person_name', 'kind', 'code', 'active'],
                    'column_labels' => ['person_name' => 'Person', 'kind' => 'Type', 'code' => 'Card / finger ID'],
                    'query' => fn () => DB::table('device_credentials')->join('users', 'users.id', '=', 'device_credentials.user_id')
                        ->select('device_credentials.*', 'users.name as person_name')->orderByDesc('device_credentials.id'),
                    'fields' => [
                        ['name' => 'user_id', 'label' => 'Person', 'type' => 'select', 'options_query' => 'people', 'rules' => 'required|integer|exists:users,id'],
                        ['name' => 'kind', 'label' => 'Type', 'type' => 'select', 'rules' => 'required|in:card,fingerprint,face', 'options' => [['value' => 'card', 'label' => 'Card'], ['value' => 'fingerprint', 'label' => 'Fingerprint'], ['value' => 'face', 'label' => 'Face']]],
                        ['name' => 'code', 'label' => 'Card / finger ID', 'type' => 'text', 'rules' => 'required|string|max:100'],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
                'punches' => [
                    'label' => 'Punch Log',
                    'subtitle' => 'Every time someone punched in or out. Entries can be added by hand for missed punches.',
                    'table' => 'punches',
                    'order' => ['punched_at', 'desc'],
                    'columns' => ['punched_at', 'person_name', 'direction', 'device_name'],
                    'column_labels' => ['punched_at' => 'Time', 'person_name' => 'Person', 'device_name' => 'Device'],
                    'query' => fn () => DB::table('punches')->join('users', 'users.id', '=', 'punches.user_id')->leftJoin('devices', 'devices.id', '=', 'punches.device_id')
                        ->select('punches.*', 'users.name as person_name', 'devices.name as device_name')->orderByDesc('punches.punched_at')->limit(500),
                    'fields' => [
                        ['name' => 'user_id', 'label' => 'Person', 'type' => 'select', 'options_query' => 'people', 'rules' => 'required|integer|exists:users,id'],
                        ['name' => 'device_id', 'label' => 'Device', 'type' => 'select', 'options_query' => ['devices', 'id', 'name', 'Manual entry'], 'rules' => 'nullable|integer|exists:devices,id'],
                        ['name' => 'punched_at', 'label' => 'Time', 'type' => 'datetime', 'rules' => 'required|date'],
                        ['name' => 'direction', 'label' => 'Direction', 'type' => 'select', 'rules' => 'required|in:in,out', 'options' => [['value' => 'in', 'label' => 'In'], ['value' => 'out', 'label' => 'Out']]],
                    ],
                ],
            ],
            'certificates' => [
                'templates' => [
                    'label' => 'Templates',
                    'subtitle' => 'Wording for certificates. Use {student_name}, {guardian}, {class}, {roll}, {admission_no} and {date}.',
                    'table' => 'certificate_templates',
                    'order' => ['name', 'asc'],
                    'columns' => ['name', 'kind', 'active'],
                    'column_labels' => ['kind' => 'Type'],
                    'delete_guard' => ['certificates', 'template_id', 'Certificates were issued from this template. Mark it inactive instead.'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Template name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:certificate_templates,name'],
                        ['name' => 'kind', 'label' => 'Type', 'type' => 'select', 'rules' => 'required|in:character,testimonial,transfer,other', 'options' => [['value' => 'character', 'label' => 'Character'], ['value' => 'testimonial', 'label' => 'Testimonial'], ['value' => 'transfer', 'label' => 'Transfer'], ['value' => 'other', 'label' => 'Other']]],
                        ['name' => 'body', 'label' => 'Wording', 'type' => 'textarea', 'rules' => 'required|string|max:5000'],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'leave' => [
                'types' => [
                    'label' => 'Leave Types',
                    'subtitle' => 'The kinds of leave people can apply for.',
                    'table' => 'leave_types',
                    'order' => ['name', 'asc'],
                    'columns' => ['name', 'days_per_year', 'paid', 'active'],
                    'column_labels' => ['days_per_year' => 'Days per year'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:leave_types,name'],
                        ['name' => 'days_per_year', 'label' => 'Days per year', 'type' => 'number', 'rules' => 'required|integer|min:0|max:366', 'default' => 10],
                        ['name' => 'paid', 'label' => 'Paid leave', 'type' => 'checkbox', 'default' => true],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'timetable' => [
                'periods' => [
                    'label' => 'Bell Schedule',
                    'subtitle' => 'The periods and breaks of the school day. The class routine is built from these.',
                    'table' => 'bell_periods',
                    'order' => ['sort_order', 'asc'],
                    'columns' => ['label', 'starts_at', 'ends_at', 'is_break', 'sort_order'],
                    'column_labels' => ['starts_at' => 'Starts at', 'ends_at' => 'Ends at', 'is_break' => 'Break', 'sort_order' => 'Order'],
                    'fields' => [
                        ['name' => 'label', 'label' => 'Period name', 'type' => 'text', 'rules' => 'required|string|max:50'],
                        ['name' => 'starts_at', 'label' => 'Starts at', 'type' => 'time', 'rules' => 'required|date_format:H:i'],
                        ['name' => 'ends_at', 'label' => 'Ends at', 'type' => 'time', 'rules' => 'required|date_format:H:i|after:starts_at'],
                        ['name' => 'is_break', 'label' => 'Break', 'type' => 'checkbox', 'default' => false],
                        ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'rules' => 'required|integer|min:0', 'default' => 0],
                    ],
                ],
            ],
            'payroll' => [
                'components' => [
                    'label' => 'Salary Components',
                    'subtitle' => 'Allowances and deductions added to every payslip.',
                    'table' => 'payroll_components',
                    'order' => ['kind', 'asc'],
                    'columns' => ['name', 'kind', 'calc', 'value', 'active'],
                    'column_labels' => ['kind' => 'Type', 'calc' => 'Calculated as', 'value' => 'Default value'],
                    'delete_guard' => ['salary_structure_items', 'component_id', 'This component is used in a salary structure. Mark it inactive instead.'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:payroll_components,name'],
                        ['name' => 'kind', 'label' => 'Type', 'type' => 'select', 'rules' => 'required|in:earning,deduction', 'options' => [['value' => 'earning', 'label' => 'Earning'], ['value' => 'deduction', 'label' => 'Deduction']]],
                        ['name' => 'calc', 'label' => 'Calculated as', 'type' => 'select', 'rules' => 'required|in:fixed,percent', 'options' => [['value' => 'fixed', 'label' => 'Fixed amount'], ['value' => 'percent', 'label' => 'Percent of basic']]],
                        ['name' => 'value', 'label' => 'Default value', 'type' => 'number', 'rules' => 'required|integer|min:0', 'default' => 0],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'sms' => [
                'templates' => [
                    'label' => 'Templates',
                    'subtitle' => 'Ready-made messages. Use {guardian}, {student} and {class}.',
                    'table' => 'sms_templates',
                    'order' => ['name', 'asc'],
                    'columns' => ['name', 'body', 'active'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Template name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:sms_templates,name'],
                        ['name' => 'body', 'label' => 'Message', 'type' => 'textarea', 'rules' => 'required|string|max:500'],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'settings' => [
                'grading' => [
                    'label' => 'Grading',
                    'subtitle' => 'Marks ranges and the grade and point each one earns.',
                    'table' => 'grade_scales',
                    'order' => ['min_marks', 'desc'],
                    'columns' => ['grade', 'min_marks', 'max_marks', 'point'],
                    'column_labels' => ['min_marks' => 'From marks', 'max_marks' => 'To marks', 'point' => 'Grade point'],
                    'fields' => [
                        ['name' => 'grade', 'label' => 'Grade', 'type' => 'text', 'rules' => 'required|string|max:5|unique:grade_scales,grade'],
                        ['name' => 'min_marks', 'label' => 'From marks', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100', 'default' => 0],
                        ['name' => 'max_marks', 'label' => 'To marks', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100|gte:min_marks', 'default' => 100],
                        ['name' => 'point', 'label' => 'Grade point', 'type' => 'number', 'rules' => 'required|numeric|min:0|max:5', 'default' => 0, 'step' => '0.01'],
                    ],
                ],
            ],
            'transport' => [
                'vehicles' => [
                    'label' => 'Vehicles',
                    'subtitle' => 'School buses and vans, their drivers and how many students they carry.',
                    'table' => 'transport_vehicles',
                    'order' => ['name', 'asc'],
                    'columns' => ['name', 'plate', 'capacity', 'riders', 'driver_name', 'driver_phone', 'route_name', 'active'],
                    'column_labels' => ['plate' => 'Plate no.', 'riders' => 'Riders', 'driver_name' => 'Driver', 'driver_phone' => 'Driver phone', 'route_name' => 'Route'],
                    'delete_guard' => ['bus_assignments', 'vehicle_id', 'Students ride this vehicle. Reassign them or mark it inactive instead.'],
                    'query' => fn () => DB::table('transport_vehicles')->leftJoin('transport_routes', 'transport_routes.id', '=', 'transport_vehicles.route_id')
                        ->select('transport_vehicles.*', 'transport_routes.name as route_name', DB::raw('(select count(*) from bus_assignments b where b.vehicle_id = transport_vehicles.id) as riders'))
                        ->orderBy('transport_vehicles.name'),
                    'fields' => [
                        ['name' => 'name', 'label' => 'Vehicle name', 'type' => 'text', 'rules' => 'required|string|max:100'],
                        ['name' => 'plate', 'label' => 'Plate no.', 'type' => 'text', 'rules' => 'nullable|string|max:30|unique:transport_vehicles,plate'],
                        ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'rules' => 'required|integer|min:1|max:200', 'default' => 40],
                        ['name' => 'driver_name', 'label' => 'Driver', 'type' => 'text', 'rules' => 'nullable|string|max:100'],
                        ['name' => 'driver_phone', 'label' => 'Driver phone', 'type' => 'text', 'rules' => 'nullable|string|max:30'],
                        ['name' => 'route_id', 'label' => 'Route', 'type' => 'select', 'options_query' => ['transport_routes', 'id', 'name', 'No fixed route'], 'rules' => 'nullable|integer|exists:transport_routes,id'],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
            'library' => [
                'categories' => [
                    'label' => 'Book Categories',
                    'subtitle' => 'Groups that books are shelved under.',
                    'table' => 'library_categories',
                    'order' => ['name', 'asc'],
                    'columns' => ['name'],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Category name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:library_categories,name'],
                    ],
                ],
                'books' => [
                    'label' => 'All Books',
                    'subtitle' => 'Every title in the library with copies on the shelf.',
                    'table' => 'library_books',
                    'order' => ['title', 'asc'],
                    'columns' => ['title', 'author', 'category_name', 'copies', 'available', 'shelf'],
                    'column_labels' => ['category_name' => 'Category', 'available' => 'Available'],
                    'query' => fn () => DB::table('library_books')
                        ->leftJoin('library_categories', 'library_categories.id', '=', 'library_books.category_id')
                        ->select(
                            'library_books.*',
                            'library_categories.name as category_name',
                            DB::raw("library_books.copies - (select count(*) from library_loans l where l.book_id = library_books.id and l.status != 'returned') as available"),
                        )
                        ->orderBy('library_books.title'),
                    'fields' => [
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'rules' => 'required|string|max:200'],
                        ['name' => 'author', 'label' => 'Author', 'type' => 'text', 'rules' => 'nullable|string|max:150'],
                        ['name' => 'isbn', 'label' => 'ISBN', 'type' => 'text', 'rules' => 'nullable|string|max:30'],
                        ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options_query' => ['library_categories', 'id', 'name', 'No category'], 'rules' => 'nullable|integer|exists:library_categories,id'],
                        ['name' => 'copies', 'label' => 'Copies', 'type' => 'number', 'rules' => 'required|integer|min:1|max:1000', 'default' => 1],
                        ['name' => 'shelf', 'label' => 'Shelf', 'type' => 'text', 'rules' => 'nullable|string|max:50'],
                    ],
                ],
                'members' => [
                    'label' => 'Library Cards',
                    'subtitle' => 'Students who can borrow books.',
                    'table' => 'library_members',
                    'order' => ['card_no', 'asc'],
                    'columns' => ['student_name', 'card_no', 'active'],
                    'column_labels' => ['student_name' => 'Student', 'card_no' => 'Card no.'],
                    'query' => fn () => DB::table('library_members')
                        ->join('users', 'users.id', '=', 'library_members.user_id')
                        ->select('library_members.*', 'users.name as student_name')
                        ->orderBy('library_members.card_no'),
                    'fields' => [
                        ['name' => 'user_id', 'label' => 'Student', 'type' => 'select', 'options_query' => 'students', 'rules' => 'required|integer|exists:users,id|unique:library_members,user_id'],
                        ['name' => 'card_no', 'label' => 'Card no.', 'type' => 'text', 'rules' => 'nullable|string|max:30|unique:library_members,card_no', 'hint' => 'Leave empty to number it automatically.'],
                        ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function resources(string $group): array
    {
        return self::groups()[$group] ?? [];
    }

    /** Route constraint for the resource part of /app/{group}/{resource}. */
    public static function slugs(string $group): string
    {
        return implode('|', array_keys(array_filter(self::resources($group), fn ($r) => ! isset($r['base']))));
    }
}
