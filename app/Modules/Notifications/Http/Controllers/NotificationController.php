<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks the notification as read and opens the page it points to, in one visit. Only the
     * path, query and fragment of the stored URL are used, so the redirect always stays on this
     * site even if the notification was created under another host (for example, before
     * APP_URL changed). A URL with no usable path sends the user back where they were.
     */
    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $target = $this->localTarget((string) ($notification->data['url'] ?? ''));

        return $target !== null ? redirect($target) : back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * The same-site part of a stored URL (path, then `?query` and `#fragment`), or null when it has no
     * path or the path would leave the site (`//host` or `/\\host` are read by browsers as another host).
     */
    private function localTarget(string $url): ?string
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? ($parts['path'] ?? null) : null;

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return null;
        }

        return $path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
