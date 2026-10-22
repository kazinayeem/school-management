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
        return implode('|', array_keys(self::resources($group)));
    }
}
