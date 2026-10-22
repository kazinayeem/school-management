<?php

namespace App\Http\Controllers;

use App\Support\Comms;
use App\Support\Listing\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $limit = Listing::limit($request);
        $unreadOnly = $request->boolean('unread');
        $q = DB::table('app_notifications')->where('user_id', $user->id)->when($unreadOnly, fn ($q) => $q->whereNull('read_at'));
        $total = (clone $q)->count();
        $pages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);

        return Inertia::render('comms/notifications', [
            'shell' => MessageController::shell($user),
            'rows' => $q->orderByDesc('id')->forPage($page, $limit)->get(['id', 'kind', 'title', 'body', 'url', 'read_at', 'created_at'])->map(fn ($n) => (array) $n)->all(),
            'filters' => ['unread' => $unreadOnly, 'limit' => $limit],
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => $pages, 'from' => $total ? ($page - 1) * $limit + 1 : 0, 'to' => min($page * $limit, $total)],
        ]);
    }

    /** Opening one marks it read and goes to what it is about. */
    public function open(Request $request, int $id): RedirectResponse
    {
        $n = DB::table('app_notifications')->where('id', $id)->where('user_id', $request->user()->id)->first();
        abort_if($n === null, 404);
        DB::table('app_notifications')->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        // Only ever to a page on this site.
        return redirect(str_starts_with($n->url, '/') && ! str_starts_with($n->url, '//') ? $n->url : Comms::base($request->user()).'/notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        DB::table('app_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    /** The counts the header shows. */
    public function counts(Request $request)
    {
        $user = $request->user();

        return response()->json(['messages' => MessageController::unreadCount($user), 'notifications' => (int) DB::table('app_notifications')->where('user_id', $user->id)->whereNull('read_at')->count()]);
    }
}
