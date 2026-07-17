<?php

namespace App\Jobs;

use App\Enums\TransactionStatus;
use App\Events\FailedPayment;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FailPendingTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $transaction_uuid;

    public function __construct(string $transaction_uuid)
    {
        $this->transaction_uuid = $transaction_uuid;
    }

    public function handle()
    {
        $transaction = Transaction::find($this->transaction_uuid);

        if (! $transaction) {
            return;
        }

        if ($transaction->status === TransactionStatus::PENDING->value) {
            $transaction->status = TransactionStatus::FAILED->value;
            $transaction->save();

            $order = $transaction->order;

            // Release the reserved stock back into the pool
            if ($order) {
                DB::transaction(function () use ($order) {
                    foreach ($order->cart_items as $item) {
                        DB::table('variants')
                            ->where('id', $item->variant_id)
                            ->decrement('reserved_stock', $item->count);
                    }
                });
            }

            FailedPayment::dispatch($transaction->order, $transaction);
        }
    }
}
