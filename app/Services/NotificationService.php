<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class NotificationService
{

    public function __construct(private Messaging $messaging) {}

    public function notifyUser(int $user_id, string $title, string $body, array $data = [] ?? null)
    {
        // 1. Save notification in DB
        $notification = Notification::create([
            'id'      => (string) Str::uuid(),
            'user_id' => $user_id,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);

        $user = User::find($user_id);
        // 2. Send push notification if FCM token exists
        if ($user->fcm_token) {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData(array_merge($data, ['notification_id' => $notification->id]));

            try {
                $this->messaging->send($message);
            } catch (\Exception $e) {
                // log but don’t break app
                \Log::error('FCM send failed: '.$e->getMessage());
                $notification->delete();
            }
        }

        return $notification;
    }


    public function getUserNotifications(User $user, int $limit = 20)
    {
        return Notification::where('user_id', $user->id)
            ->latest()
            ->take($limit)
            ->get();
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->update(['read_at' => now()]);
    }

    public function markAllAsRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(Notification $notification): void
    {
        $notification->delete();
    }

    public function clearAll(User $user): void
    {
        Notification::where('user_id', $user->id)->delete();
    }


}
