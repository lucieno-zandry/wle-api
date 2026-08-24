<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionAuditLog;
use App\Models\User;
use Illuminate\Support\Str;

// app/Services/TransactionRefundService.php
class TransactionRefundService
{
    public function refund(Transaction $transaction, float $amount, string $reason, int $performedBy): Transaction
    {
        $refund = Transaction::create([
            'uuid'                     => Str::uuid()->toString(),
            'user_id'                  => $transaction->user_id,
            'order_uuid'               => $transaction->order_uuid,
            'payment_method'                   => $transaction->method ?? "Unknown",
            'amount'                   => $amount ?? $transaction->amount,
            'status'                   => TransactionStatus::PENDING->value,
            'type'                     => 'REFUND',
            'parent_transaction_uuid'  => $transaction->uuid,
            'informations'             => ['reason' => $reason],
            'payment_url'              => null,
        ]);

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => $performedBy,
            'action'           => 'refund_initiated',
            'old_value'        => null,
            'new_value'        => $refund->uuid,
            'reason'           => $reason,
            'metadata'         => ['ip' => request()->ip(), 'refund_amount' => $refund->amount],
        ]);

        return $refund;
    }
}
