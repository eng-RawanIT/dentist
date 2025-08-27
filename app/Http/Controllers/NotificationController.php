<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = Auth::user();
        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated']);
    }

    public function index(NotificationService $service, Request $request)
    {
        return $service->getUserNotifications($request->user());
    }

    public function markAsRead(Notification $notification, NotificationService $service)
    {
        if(Auth::id() === $notification->user_id){
            $service->markAsRead($notification);
            return response()->json(['message' => 'Marked as read']);
        }
        return response()->json(['message' => 'you are not allowed']);
    }

    public function delete(Notification $notification, NotificationService $service)
    {
        if(Auth::id() === $notification->user_id){
            $service->delete($notification);
            return response()->json(['message' => 'deleted']);
        }
        return response()->json(['message' => 'you are not allowed']);
    }

    public function clearAll(NotificationService $service, Request $request)
    {
        $service->clearAll($request->user());

        return response()->json(['message' => 'All notifications cleared']);

    }

    /*
   $notification = $this->notificationService->notifyUser(
          $user,
          "Welcome to Dentech Smile 🎉",
          "Thank you for joining our platform. Let's start your journey!",
          [
              'type' => 'welcome',
              'action' => 'open_dashboard',
          ]
      );
   */
}
