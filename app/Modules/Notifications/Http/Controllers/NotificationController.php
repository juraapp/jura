<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks the notification as read and opens the page it points to, in one visit. Only the
     * path of the stored URL is used, so the redirect always stays on this site even if the
     * notification was created under another host (for example, before APP_URL changed).
     */
    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $path = parse_url((string) ($notification->data['url'] ?? ''), PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/') ? redirect($path) : back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
