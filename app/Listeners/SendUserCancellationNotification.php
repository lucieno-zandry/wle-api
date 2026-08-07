<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Notifications\UserOrderCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendUserCancellationNotification implements ShouldQueue
{
    public function handle(OrderCancelled $event): void
    {
        $event->order->user->notify(new UserOrderCancelledNotification(
            $event->order, 
            $event->refundRequest
        ));
    }
}