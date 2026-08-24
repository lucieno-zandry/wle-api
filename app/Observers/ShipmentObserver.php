<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;

class ShipmentObserver
{
    public function created(Shipment $shipment)
    {
        $order = $shipment->order;

        if (!$order) return;

        if ($shipment->status === ShipmentStatus::SHIPPED->value) {
            $order->status = OrderStatus::SHIPPED;
        } else if ($shipment->status === ShipmentStatus::DELIVERED->value) {
            $order->status = OrderStatus::DELIVERED;
        } else if ($shipment->status === ShipmentStatus::PROCESSING->value) {
            $order->status = OrderStatus::PROCESSING;
        }

        $order->save();
    }
}
