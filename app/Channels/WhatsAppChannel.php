<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'toWhatsApp')) {
            $message = $notification->toWhatsApp($notifiable);
            $phone = $notifiable->routeNotificationFor('whatsApp', $notification);

            // Stub implementation for WA Provider (Fonnte/Wablas)
            // Example logic:
            // $phone = $notifiable->phone;
            // Http::post('https://api.fonnte.com/send', [
            //     'target' => $phone,
            //     'message' => $message,
            // ])->withHeaders(['Authorization' => env('FONNTE_TOKEN')]);

            // For now we just log it
            Log::info("Sending WhatsApp to {$notifiable->name} ({$phone}): {$message}");
        }
    }
}
