<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

/** Notifikasi dalam aplikasi (US-20, layar 9). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->appNotifications()->where('channel', 'IN_APP')->latest('id')->paginate(25);

        return view('notifications.index', compact('items'));
    }

    public function open(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        $url = $notification->url && str_starts_with($notification->url, url('/')) ? $notification->url : route('notifications.index');

        return redirect($url);
    }

    public function readAll(Request $request)
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
