<?php

// app/Http/Controllers/RefundRequestController.php (new controller)
namespace App\Http\Controllers;

use App\Models\RefundRequest;
use App\Http\Requests\RefundRequestApproveRequest;
use App\Http\Requests\RefundRequestRejectRequest;
use App\Notifications\RefundApproved;
use App\Notifications\RefundRejected;
use App\Services\TransactionRefundService;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = RefundRequest::withRelations()
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        /** @var \App\Models\RefundRequest */
        foreach ($requests as $request)
            $request->convertCurrency();

        return ['refund_requests' => $requests];
    }

    public function approve(RefundRequestApproveRequest $request, RefundRequest $refundRequest)
    {
        if ($refundRequest->status !== 'pending') {
            return response()->json(['message' => 'Request already processed.'], 422);
        }

        // Call the existing refund method on the transaction
        $transaction = $refundRequest->transaction;

        if ($request->transaction_reference)
            $transaction->payment_reference = $request->transaction_reference;

        if ($request->payment_method)
            $transaction->payment_method = $request->payment_method;

        $refundTransaction = app(TransactionRefundService::class)->refund(
            transaction: $transaction,
            amount: $refundRequest->amount,
            informations: ['reason' => $refundRequest->reason, 'reference' => $request->reference || ""],
            performedBy: auth('sanctum')->id()
        );

        $refundRequest->update([
            'status'       => 'approved',
            'admin_notes'  => $request->admin_notes,
            'reviewed_by'  => auth('sanctum')->id(),
            'reviewed_at'  => now(),
        ]);

        // Notify customer
        $transaction->user->notify(new RefundApproved($refundRequest, $refundTransaction));

        return ['refund_request' => $refundRequest->fresh()];
    }

    public function reject(RefundRequestRejectRequest $request, RefundRequest $refundRequest)
    {
        if ($refundRequest->status !== 'pending') {
            return response()->json(['message' => 'Request already processed.'], 422);
        }

        $refundRequest->update([
            'status'       => 'rejected',
            'admin_notes'  => $request->admin_notes,
            'reviewed_by'  => auth('sanctum')->id(),
            'reviewed_at'  => now(),
        ]);

        // Notify the customer – use the relationship to get the user
        $refundRequest->transaction->user->notify(new RefundRejected($refundRequest));

        return ['refund_request' => $refundRequest->fresh()];
    }
}
