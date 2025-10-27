<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $user;

    public function __construct(Request $request)
    {
        $this->user = $request->user();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications",
     *     summary="Get a list of notifications",
     *     description="Retrieve a list of notifications for the authenticated user",
     *     tags={"Notification"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1, description="Notification ID"),
     *                 @OA\Property(property="message", type="string", example="You have a new message", description="Notification message"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-15T14:15:00.000000Z", description="Notification creation timestamp")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false, description="Operation status"),
     *             @OA\Property(property="message", type="string", example="Invalid request parameters", description="Error message")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false, description="Operation status"),
     *             @OA\Property(property="message", type="string", example="Unauthorized", description="Error message")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json($this->user->notifications());
    }

    public function unread()
    {
        return response()->json($this->user->unreadNotifications());
    }

    public function markAsRead(Request $request)
    {
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

    public function markAllAsRead()
    {
        $this->user->unreadNotifications()->update(['read' => 1]);
        return response()->json(['status' => true, 'message' => 'All notifications marked as read.']);
    }
}
