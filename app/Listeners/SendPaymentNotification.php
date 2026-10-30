<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Notifications\PaymentSuccessNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentSucceeded $event): void
    {
        $guardian = $event->payment->student?->guardian;

        if (! $guardian || (! $guardian->email && ! $guardian->phone)) {
            return;
        }

        $guardian->notify(new PaymentSuccessNotification($event->payment));
    }
}
