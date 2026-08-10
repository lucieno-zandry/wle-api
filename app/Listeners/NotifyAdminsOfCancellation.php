<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Models\User;
use App\Notifications\AdminOrderCancelledNotification;
use App\Services\AdminService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfCancellation implements ShouldQueue
{
    public function handle(OrderCancelled $event): void
    {
        // Fetch all admins
        app(AdminService::class)->notify(new AdminOrderCancelledNotification(
            $event->order,
            $event->refundRequest,
            $event->reason
        ));
    }
}
