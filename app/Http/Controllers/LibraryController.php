<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function give(Request $request): Response
    {
        $this->guard($request, '/app/library');

        return Inertia::render('office/give-book', [
            ...$this->shared($request),
            'books' => $this->booksQuery()->orderBy('library_books.title')->get()->filter(fn ($b) => $b->available > 0)->map(fn ($b) => ['id' => $b->id, 'title' => $b->title, 'author' => $b->author])->values(),
            'loanDays' => SiteSettings::int('library_loan_days', 14),
            'today' => today()->toDateString(),
            'recent' => $this->loansQuery()->orderByDesc('library_loans.id')->limit(10)->get(),
        ]);
    }

    public function storeLoan(Request $request): RedirectResponse
    {
        $this->guard($request, '/app/library');
        $data = $request->validate([
            'member' => ['required', 'string', 'max:100'],
            'book_id' => ['required', 'integer', 'exists:library_books,id'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $member = DB::table('library_members')
            ->join('users', 'users.id', '=', 'library_members.user_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where(fn ($q) => $q->where('library_members.card_no', $data['member'])->orWhere('student_profiles.admission_no', $data['member'])->orWhere('users.username', $data['member']))
            ->first(['library_members.*', 'users.name']);

        if ($member === null) {
            return back()->withErrors(['member' => 'No library card found for that card, admission number or username.']);
        }
        if (! $member->active) {
            return back()->withErrors(['member' => 'This library card is not active.']);
        }

        $max = SiteSettings::int('library_max_books', 3);
        if (DB::table('library_loans')->where('user_id', $member->user_id)->where('status', '!=', 'returned')->count() >= $max) {
            return back()->withErrors(['member' => "This member already has {$max} books out."]);
        }

        $book = $this->booksQuery()->where('library_books.id', $data['book_id'])->first(['library_books.*']);
        if ($book->available < 1) {
            return back()->withErrors(['book_id' => 'No copies of this book are on the shelf.']);
        }

        DB::transaction(function () use ($member, $book, $data) {
            DB::table('library_loans')->insert([
                'user_id' => $member->user_id, 'title' => $book->title, 'author' => $book->author, 'card_no' => $member->card_no,
                'member_since' => substr((string) $member->created_at, 0, 10), 'issued_on' => today()->toDateString(),
                'due_on' => $data['due_on'] ?? today()->addDays(SiteSettings::int('library_loan_days', 14))->toDateString(),
                'fine' => 0, 'status' => 'on loan', 'book_id' => $book->id, 'fine_paid' => false,
            ]);
            DB::table('library_reservations')->where('book_id', $book->id)->where('user_id', $member->user_id)->whereIn('status', ['waiting', 'ready'])
                ->update(['status' => 'fulfilled', 'updated_at' => now()]);
        });

        return back()->with('status', 'Book issued to '.$member->name.'.');
    }

    public function desk(Request $request): Response
    {
        $this->guard($request, '/app/library/desk');
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('office/loans', [
            ...$this->shared($request),
            'mode' => 'desk',
            'rows' => $this->openLoans($search),
            'search' => $search,
            'finePerDay' => SiteSettings::int('library_fine_per_day', 5),
        ]);
    }

    public function returnLoan(Request $request, int $id): RedirectResponse
    {
        $this->guard($request, '/app/library/desk');
        $loan = DB::table('library_loans')->where('id', $id)->first();
        abort_if($loan === null, 404);
        if ($loan->status === 'returned') {
            return back()->with('error', 'This book was already returned.');
        }

        $fine = $this->fineFor($loan->due_on);
        DB::table('library_loans')->where('id', $id)->update([
            'status' => 'returned', 'returned_on' => today()->toDateString(), 'fine' => $fine, 'fine_paid' => $fine === 0,
        ]);

        return back()->with('status', $fine > 0 ? 'Book returned. A fine of '.$fine.' is due.' : 'Book returned.');
    }

    public function fines(Request $request): Response
    {
        $this->guard($request, '/app/library/fines');

        return Inertia::render('office/loans', [
            ...$this->shared($request),
            'mode' => 'fines',
            'rows' => $this->openLoans('', true),
            'unpaid' => $this->loansQuery()->where('library_loans.status', 'returned')->where('library_loans.fine', '>', 0)->where('library_loans.fine_paid', false)->orderByDesc('library_loans.id')->get(),
            'search' => '',
            'finePerDay' => SiteSettings::int('library_fine_per_day', 5),
        ]);
    }

    public function payFine(Request $request, int $id): RedirectResponse
    {
        $this->guard($request, '/app/library/fines');
        $updated = DB::table('library_loans')->where('id', $id)->where('status', 'returned')->where('fine', '>', 0)->update(['fine_paid' => true]);
        abort_if($updated === 0, 404);

        return back()->with('status', 'Fine marked as paid.');
    }

    public function reservations(Request $request): Response
    {
        $this->guard($request, '/app/library/reservations');

        return Inertia::render('office/reservations', [
            ...$this->shared($request),
            'rows' => DB::table('library_reservations')
                ->join('users', 'users.id', '=', 'library_reservations.user_id')
                ->join('library_books', 'library_books.id', '=', 'library_reservations.book_id')
                ->orderByRaw("case library_reservations.status when 'ready' then 0 when 'waiting' then 1 else 2 end")->orderBy('library_reservations.id')
                ->limit(200)->get(['library_reservations.*', 'users.name', 'library_books.title']),
            'books' => DB::table('library_books')->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $this->guard($request, '/app/library/reservations');
        $data = $request->validate(['member' => ['required', 'string', 'max:100'], 'book_id' => ['required', 'integer', 'exists:library_books,id']]);

        $member = DB::table('library_members')->join('users', 'users.id', '=', 'library_members.user_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('library_members.active', true)
            ->where(fn ($q) => $q->where('library_members.card_no', $data['member'])->orWhere('student_profiles.admission_no', $data['member'])->orWhere('users.username', $data['member']))
            ->value('library_members.user_id');

        if ($member === null) {
            return back()->withErrors(['member' => 'No active library card found for that card, admission number or username.']);
        }
        if (DB::table('library_reservations')->where('book_id', $data['book_id'])->where('user_id', $member)->whereIn('status', ['waiting', 'ready'])->exists()) {
            return back()->withErrors(['book_id' => 'This member has already reserved this book.']);
        }

        DB::table('library_reservations')->insert(['book_id' => $data['book_id'], 'user_id' => $member, 'reserved_on' => today()->toDateString(), 'status' => 'waiting', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Reservation added.');
    }

    public function updateReservation(Request $request, int $id): RedirectResponse
    {
        $this->guard($request, '/app/library/reservations');
        $data = $request->validate(['status' => ['required', Rule::in(['ready', 'cancelled'])]]);
        $updated = DB::table('library_reservations')->where('id', $id)->whereIn('status', ['waiting', 'ready'])->update(['status' => $data['status'], 'updated_at' => now()]);
        abort_if($updated === 0, 404);

        return back()->with('status', 'Reservation updated.');
    }

    private function fineFor(?string $dueOn): int
    {
        $late = $dueOn ? (int) max(0, today()->diffInDays($dueOn, false) * -1) : 0;

        return $late * SiteSettings::int('library_fine_per_day', 5);
    }

    private function openLoans(string $search, bool $overdueOnly = false): \Illuminate\Support\Collection
    {
        return $this->loansQuery()
            ->where('library_loans.status', '!=', 'returned')
            ->when($overdueOnly, fn ($q) => $q->where('library_loans.due_on', '<', today()->toDateString()))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('library_loans.title', 'like', "%{$search}%")->orWhere('library_loans.card_no', 'like', "%{$search}%")))
            ->orderBy('library_loans.due_on')->limit(300)->get()
            ->map(function ($row) {
                $row->accrued = $this->fineFor($row->due_on);

                return $row;
            });
    }

    private function loansQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('library_loans')->join('users', 'users.id', '=', 'library_loans.user_id')
            ->select('library_loans.id', 'library_loans.title', 'library_loans.card_no', 'library_loans.issued_on', 'library_loans.due_on', 'library_loans.returned_on', 'library_loans.fine', 'library_loans.fine_paid', 'library_loans.status', 'users.name');
    }

    private function booksQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('library_books')->select('library_books.*')->selectRaw("library_books.copies - (select count(*) from library_loans l where l.book_id = library_books.id and l.status != 'returned') as available");
    }

    private function shared(Request $request): array
    {
        $role = $request->user()->role;

        return ['navigation' => OfficeMenu::for($role), 'roleLabel' => $role === 'admin' ? 'Super Admin' : 'Teacher'];
    }

    private function guard(Request $request, string $path): void
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, ['admin', 'staff'], true) && OfficeMenu::allows($role, $path), 403);
    }
}
