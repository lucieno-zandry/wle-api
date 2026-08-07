<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminOrderCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order, 
        public ?RefundRequest $refundRequest, 
        public string $reason
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Alert: Order Cancelled - ' . $this->order->uuid)
            ->greeting('Hello Admin,')
            ->line('An order has been cancelled by the customer.')
            ->line('Order UUID: ' . $this->order->uuid)
            ->line('Reason: ' . $this->reason);

        if ($this->refundRequest) {
            $mail->line('A refund request has been automatically generated and awaits approval.');
            $mail->action('Review Refund', url('/admin/refunds/' . $this->refundRequest->uuid));
        }

        return $mail;
    }

    public function toArray($notifiable): array
    {
        // Strictly matches TS RefundNotificationData type
        return [
            'notification_type' => 'refund',
            'type' => 'refund_requested',
            'order_uuid' => $this->order->uuid,
            'refund_request_uuid' => $this->refundRequest?->uuid,
            'amount' => $this->refundRequest?->amount ?? 0,
            'customer_name' => $this->order->user->name,
            'reason' => $this->reason,
            'message' => "Order {$this->order->uuid} cancelled. Action required on refund."
        ];
    }
}