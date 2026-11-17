<?php

namespace App\Http\Controllers\Client\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** BN-17: thông báo trong ứng dụng. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('client.account.notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(15),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }
}
