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
    public function refund(Transaction $transaction, float $amount, array $informations, int $performedBy): Transaction
    {
        $refund = Transaction::create([
            'uuid'                     => Str::uuid()->toString(),
            'user_id'                  => $transaction->user_id,
            'order_uuid'               => $transaction->order_uuid,
            'payment_method'                   => $transaction->payment_method ?? "Unknown",
            'amount'                   => $amount ?? $transaction->amount,
            'type'                     => 'REFUND',
            'parent_transaction_uuid'  => $transaction->uuid,
            'informations'             => $informations,
            'payment_url'              => null,
            'payment_reference' => $transaction->payment_reference,
        ]);

        $refund->status = TransactionStatus::SUCCESS->value;
        $refund->save();

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => $performedBy,
            'action'           => 'refund_initiated',
            'old_value'        => null,
            'new_value'        => $refund->uuid,
            'reason'           => $informations['reason'],
            'metadata'         => ['ip' => request()->ip(), 'refund_amount' => $refund->amount],
        ]);

        return $refund;
    }
}
