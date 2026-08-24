<?php

namespace App\Services;

use App\Models\RefundRequest;
use App\Models\Transaction;
use App\Notifications\RefundRequested;
use Illuminate\Support\Str;

class RefundService
{
    function requestRefund(Transaction $transaction, string $reason)
    {
        $refundRequest = RefundRequest::create([
            'uuid'              => Str::uuid()->toString(),
            'user_id'           => auth('sanctum')->id(),
            'transaction_uuid'  => $transaction->uuid,
            'order_uuid' => $transaction->order_uuid,
            'amount'            => $request->amount ?? $transaction->amount,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        app(AdminService::class)->notify(new RefundRequested($refundRequest, $transaction, auth('sanctum')->user()));

        return $refundRequest;
    }
}
