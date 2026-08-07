<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UserOrderCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?RefundRequest $refundRequest
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Order Cancellation Confirmed')
            ->greeting('Hello ' . $this->order->user->name . ',')
            ->line('Your order (' . $this->order->uuid . ') has been successfully cancelled.');

        if ($this->refundRequest) {
            $mail->line('Your refund request for ' . $this->refundRequest->amount . ' is currently processing. You will receive an update once it is approved.');
        }

        return $mail->line('Thank you for shopping with us!');
    }

    public function toArray($notifiable): array
    {
        // Matches TS OtherNotificationData / System notification
        return [
            'notification_type' => 'system',
            'title' => 'Order Cancelled',
            'message' => 'Your order (' . $this->order->uuid . ') was successfully cancelled.' .
                ($this->refundRequest ? ' Your refund is pending.' : '')
        ];
    }
}