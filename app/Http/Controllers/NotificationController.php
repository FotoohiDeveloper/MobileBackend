<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $user;

    public function __construct(Request $request) {
        $this->user = $request->user();
    }

    public function index() {
        return response()->json($this->user->notifications());
    }

    public function unread() {
        return response()->json($this->user->unreadNotifications());
    }

    public function markAsRead(Request $request) {
        $data = $request->validate([
            'notification_id' => 'required|integer|exists:notifications,id',
        ]);

        $notification = $this->user->notifications()->find($data['notification_id']);
        if ($notification) {
            $notification->update(['read' => 1]);
            return response()->json(['status' => true, 'message' => 'Notification marked as read.']);
        }

        return response()->json(['status' => false, 'message' => 'Notification not found.'], 404);
    }

    public function markAllAsRead() {
        $this->user->unreadNotifications()->update(['read' => 1]);
        return response()->json(['status' => true, 'message' => 'All notifications marked as read.']);
    }
}
