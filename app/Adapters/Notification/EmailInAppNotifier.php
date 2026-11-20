<?php

namespace App\Adapters\Notification;

use App\Mail\BljNotificationMail;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class EmailInAppNotifier implements Notifier
{
    public function send(User $user, string $type, string $title, string $body, ?string $url = null, array $channels = ['IN_APP', 'EMAIL']): void
    {
        if (in_array('IN_APP', $channels, true)) {
            AppNotification::create(['user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url,
                'channel' => 'IN_APP', 'sent_at' => now()]);
        }
        $email = $user->employee?->email;
        if (in_array('EMAIL', $channels, true) && $email && ! config('blj.mute_email')) {
            Mail::to($email)->queue(new BljNotificationMail($title, $body, $url, $user->displayName())); // lewat antrian
            AppNotification::create(['user_id' => $user->id, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url,
                'channel' => 'EMAIL', 'sent_at' => now(), 'read_at' => now()]);
        }
    }
}
