<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Models\User;
use App\Notifications\AdminOrderCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfCancellation implements ShouldQueue
{
    public function handle(OrderCancelled $event): void
    {
        // Fetch all admins
        $admins = User::where('role', 'admin')->get();

        Notification::send($admins, new AdminOrderCancelledNotification(
            $event->order, 
            $event->refundRequest, 
            $event->reason
        ));
    }
}