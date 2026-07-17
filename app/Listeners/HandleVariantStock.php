<?php

namespace App\Listeners;

use App\Events\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class HandleVariantStock
{
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
    public function handle(Payment $event): void
    {

        $order = $event->order;

        DB::transaction(function () use ($order) {
            foreach ($order->cart_items as $item) {
                $variant = DB::table('variants')
                    ->where('id', $item->variant_id);

                $variant->decrement('stock', $item->count);          // Deduct physical stock
                $variant->decrement('reserved_stock', $item->count); // Deduct the reserved reservation
            }
        });
    }
}
