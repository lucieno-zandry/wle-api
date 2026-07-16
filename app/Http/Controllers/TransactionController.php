<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Events\FailedPayment;
use App\Events\Payment;
use App\Events\TransactionAudited;
use App\Helpers\Functions;
use App\Http\Requests\RefundRequestStoreRequest;
use App\Http\Requests\TransactionCreateRequest;
use App\Http\Requests\TransactionDeleteRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Http\Requests\TransactionOverrideStatusRequest;
use App\Http\Requests\TransactionRefundRequest;
use App\Http\Requests\TransactionBulkReviewRequest;
use App\Http\Requests\TransactionBulkExportRequest;
use App\Http\Requests\TransactionIndexRequest;
use App\Models\Transaction;
use App\Models\TransactionAuditLog;
use App\Jobs\FailPendingTransaction;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\DisputeCancelled;
use App\Notifications\DisputeOpened;
use App\Notifications\DisputeResolved;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSuccess;
use App\Notifications\RefundRequested;
use App\Services\CurrencyService;
use App\Services\TransactionRefundService;
use App\Services\VanillaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Psy\debug;

class TransactionController extends Controller
{
    // -------------------------------------------------------------------------
    // LIST
    // -------------------------------------------------------------------------

    public function index(TransactionIndexRequest $request)
    {
        $validated = $request->validated();

        $perPage = (int) ($validated['per_page'] ?? 25);

        // Allowlist for sorting to prevent SQL injection
        $allowedSortBy = ['created_at', 'amount', 'status'];
        $sortBy = in_array($validated['sort_by'] ?? 'created_at', $allowedSortBy)
            ? $validated['sort_by']
            : 'created_at';

        $sortDir = $validated['sort_dir'] ?? 'desc';

        $query = Transaction::withRelations()
            ->customerFilterable();

        // Helper to check "meaningful" presence (not null and not empty string)
        $has = function ($key) use ($validated) {
            return array_key_exists($key, $validated) && $validated[$key] !== null && $validated[$key] !== '';
        };

        if ($has('status')) {
            $query->where('status', $validated['status']);
        }

        if ($has('method')) {
            $query->where('payment_method', $validated['method']);
        }

        if ($has('type')) {
            $query->where('type', $validated['type']);
        }

        if ($has('dispute_status')) {
            $query->where('dispute_status', $validated['dispute_status']);
        }

        if ($has('reviewed')) {
            if ($validated['reviewed'] === 'yes') {
                $query->where('reviewed', true);
            } else { // 'no'
                $query->where(function ($q) {
                    $q->where('reviewed', false)->orWhereNull('reviewed');
                });
            }
        }

        if ($has('date_from')) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if ($has('date_to')) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if ($has('amount_min')) {
            // Accept numeric strings or numbers; ignore non-numeric empty values
            $min = $validated['amount_min'];
            if ($min !== '') {
                $query->where('amount', '>=', (float) $min);
            }
        }

        if ($has('amount_max')) {
            $max = $validated['amount_max'];
            if ($max !== '') {
                $query->where('amount', '<=', (float) $max);
            }
        }

        if ($has('order_uuid')) {
            $query->where('order_uuid', $validated['order_uuid']);
        }

        if ($has('search')) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('order_uuid', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Apply safe sorting
        $query->orderBy($sortBy, $sortDir);

        $transactions = $query->paginate($perPage)->appends($request->query());

        /** @var \App\Models\Transaction */
        foreach ($transactions as $transaction)
            $transaction->convertCurrency();

        return ['transactions' => $transactions];
    }


    // -------------------------------------------------------------------------
    // DETAIL
    // -------------------------------------------------------------------------

    public function show(string $transaction_uuid)
    {
        /** @var \App\Models\Transaction | null */
        $transaction = Transaction::withRelations()
            ->customerFilterable()
            ->find($transaction_uuid);

        $transaction?->convertCurrency();

        return ['transaction' => $transaction];
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    // In the store method, replace the old 'method' handling:
    public function store(TransactionCreateRequest $request, CurrencyService $currencyService)
    {
        $data = $request->validated();
        $data['user_id'] = auth('sanctum')->id();
        $data['type']    = 'PAYMENT';

        $amount = $request->amount;
        $currency = $currencyService->getFrom();

        $uuid = Str::uuid()->toString();
        $data['uuid'] = $uuid;

        $token = request()->header('Authorization');
        $redirect_url = Functions::get_order_detail_page_url($request->order_uuid);

        $payment_url = "";

        switch ($request->payment_method) {
            case 'vanilla_pay':
                $notif_url = route('webhooks.vanillapay');
                Log::debug('########################### TransactionController->store ###############################');
                Log::debug(["uuid", $uuid]);
                Log::debug(["notif_url", $notif_url]);
                Log::debug('---------------------------END TransactionController->store-----------------------------');

                if ($currency !== 'MGA' && $currency !== 'EUR') {
                    $amount = $currencyService->convert(amount: $amount, from: $currency, to: 'EUR');
                    $currency = ('EUR');
                }

                $payment_url = (new VanillaPayService)->initiatePayment([
                    'montant' => $amount,
                    'reference' => $uuid,
                    'panier' => substr($request->order_uuid, 0, 8),
                    'notif_url' => $notif_url,
                    'redirect_url' => $redirect_url,
                    'devise' => $currency,
                    'mode_paiement' => 'mobile_money',
                ]);

                break;

            default:
                $payment_url = config('urls.payment_host') . "/index.html?amount={$amount}&transaction_uuid={$uuid}&token={$token}&redirect_url={$redirect_url}&currency={$currency}";
                break;
        }

        // Extract searchable reference from informations if present
        if (!empty($data['informations']['reference'])) {
            $data['payment_reference'] = $data['informations']['reference'];
        }

        $data['informations']['payment_url'] = urlencode($payment_url);

        $transaction = Transaction::create($data);

        FailPendingTransaction::dispatch($transaction->uuid)
            ->delay(now()->addMinutes(10));

        Payment::dispatchIf($transaction->status === TransactionStatus::SUCCESS->value, $transaction->order, $transaction);
        FailedPayment::dispatchIf($transaction->status === TransactionStatus::FAILED->value, $transaction->order, $transaction);

        return ['transaction' => $transaction];
    }

    // In the export method, adjust the CSV header and row data:
    public function export(TransactionBulkExportRequest $request): StreamedResponse
    {
        $transactions = Transaction::applyFilters()
            ->customerFilterable()
            ->with(['user', 'order'])
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            // Updated header row
            fputcsv($handle, [
                'ID',
                'UUID',
                'Type',
                'Order UUID',
                'User Name',
                'User Email',
                'Amount',
                'Payment Method',
                'Card Brand',
                'Payment Method Label',
                'Status',
                'Payment Reference',
                'Reviewed At',
                'Dispute Status',
                'Created At',
                'Deleted At',
            ]);

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->id,
                    $t->uuid,
                    $t->type,
                    $t->order_uuid,
                    $t->user?->name,
                    $t->user?->email,
                    $t->amount,
                    $t->payment_method,
                    $t->card_brand,
                    $t->payment_method_label,
                    $t->status,
                    $t->payment_reference,
                    $t->reviewed_at,
                    $t->dispute_status,
                    $t->created_at,
                    $t->deleted_at,
                ]);
            }

            fclose($handle);
        }, 'transactions.csv', $headers);
    }

    // -------------------------------------------------------------------------
    // UPDATE (gateway callback — status + informations only)
    // -------------------------------------------------------------------------

    public function update(TransactionUpdateRequest $request, Transaction $transaction)
    {
        $oldStatus = $transaction->status;
        $data      = $request->validated();

        // Keep payment_reference in sync if informations are updated
        if (!empty($data['informations']['reference'])) {
            $data['payment_reference'] = $data['informations']['reference'];
        }

        $transaction->update($data);

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            TransactionAudited::dispatch([
                'transaction_uuid' => $transaction->uuid,
                'performed_by'     => null, // system/gateway
                'action'           => 'status_updated',
                'old_value'        => $oldStatus,
                'new_value'        => $data['status'],
                'reason'           => 'Gateway callback',
                'metadata'         => ['ip' => request()->ip()],
            ]);
        }

        Payment::dispatchIf($transaction->status === TransactionStatus::SUCCESS->value, $transaction->order, $transaction);
        FailedPayment::dispatchIf($transaction->status === TransactionStatus::FAILED->value, $transaction->order, $transaction);

        return ['transaction' => $transaction];
    }

    // -------------------------------------------------------------------------
    // DELETE (soft-delete, admin only)
    // -------------------------------------------------------------------------

    public function destroy(TransactionDeleteRequest $request)
    {
        $uuids     = $request->transaction_uuids;
        $deleted = Transaction::whereIn('id', $uuids)->delete();

        // Audit every soft-deleted transaction
        $transactions = Transaction::withTrashed()->whereIn('uuid', $uuids)->get();

        foreach ($transactions as $t) {
            TransactionAuditLog::create([
                'transaction_uuid' => $t->uuid,
                'performed_by'     => auth('sanctum')->id(),
                'action'           => 'soft_deleted',
                'reason'           => $request->input('reason'),
                'metadata'         => ['ip' => request()->ip()],
            ]);
        }

        return ['deleted' => $deleted];
    }

    // -------------------------------------------------------------------------
    // MANUAL STATUS OVERRIDE  (admin only — logs every change)
    // -------------------------------------------------------------------------

    public function overrideStatus(TransactionOverrideStatusRequest $request, Transaction $transaction)
    {
        $oldStatus = $transaction->status;
        $newStatus = $request->status;

        $transaction->update(['status' => $newStatus]);

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => auth('sanctum')->id(),
            'action'           => 'status_override',
            'old_value'        => $oldStatus,
            'new_value'        => $newStatus,
            'reason'           => $request->reason,
            'metadata'         => [
                'ip'         => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);

        // Fire domain events if the new status warrants it
        Payment::dispatchIf($newStatus === TransactionStatus::SUCCESS->value, $transaction->order, $transaction);
        FailedPayment::dispatchIf($newStatus === TransactionStatus::FAILED->value, $transaction->order, $transaction);

        return ['transaction' => $transaction->fresh()];
    }

    // -------------------------------------------------------------------------
    // REFUND  (creates a new REFUND-type transaction linked to the original)
    // -------------------------------------------------------------------------

    public function refund(TransactionRefundRequest $request, Transaction $transaction)
    {
        $amount = $request->amount ?? $transaction->amount;
        $refund = app(TransactionRefundService::class)->refund(
            transaction: $transaction,
            amount: $amount,
            reason: $request->reason,
            performedBy: auth('sanctum')->id()
        );

        return [
            'refund_transaction' => $refund
        ];
    }

    // -------------------------------------------------------------------------
    // RESEND NOTIFICATION
    // -------------------------------------------------------------------------

    public function resendNotification(Request $request, Transaction $transaction)
    {
        $user = $transaction->user;
        $order_detail_url = Functions::get_order_detail_page_url($transaction->order->uuid);

        match ($transaction->status) {
            TransactionStatus::SUCCESS->value => $user->notify(new PaymentSuccess($transaction, $transaction->order, $order_detail_url)),
            TransactionStatus::FAILED->value  => $user->notify(new PaymentFailed($transaction, $transaction->order, $order_detail_url)),
            default                           => null,
        };

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => auth('sanctum')->id(),
            'action'           => 'notification_resent',
            'metadata'         => ['ip' => request()->ip(), 'status' => $transaction->status],
        ]);

        return ['message' => 'Notification sent successfully.'];
    }

    // -------------------------------------------------------------------------
    // BULK REVIEW  (mark multiple transactions as "reviewed" by the current admin)
    // -------------------------------------------------------------------------

    public function bulkReview(TransactionBulkReviewRequest $request)
    {
        $uuids = $request->transaction_uuids;

        Transaction::whereIn('uuid', $uuids)
            ->whereNull('reviewed_at')
            ->update([
                'reviewed_at' => now(),
                'reviewed_by' => auth('sanctum')->id(),
            ]);

        $logs = collect($uuids)->map(fn($uuid) => [
            'transaction_uuid' => $uuid,
            'performed_by'     => auth('sanctum')->id(),
            'action'           => 'reviewed',
            'metadata'         => json_encode(['ip' => request()->ip()]),
            'created_at'       => now(),
        ])->toArray();

        TransactionAuditLog::insert($logs);

        return ['message' => count($uuids) . ' transactions marked as reviewed.'];
    }

    // -------------------------------------------------------------------------
    // EXPORT  (filtered CSV download)
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // WEBHOOK LOGS  (view raw gateway callbacks for a transaction)
    // -------------------------------------------------------------------------

    public function webhookLogs(Request $request, Transaction $transaction)
    {
        $logs = $transaction->webhook_logs()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ['webhook_logs' => $logs];
    }

    // -------------------------------------------------------------------------
    // AUDIT TRAIL  (full history of manual changes for a transaction)
    // -------------------------------------------------------------------------

    public function auditLogs(Request $request, Transaction $transaction)
    {
        $logs = $transaction->audit_logs()
            ->with('performed_by_user')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return ['audit_logs' => $logs];
    }

    // -------------------------------------------------------------------------
    // DISPUTE MANAGEMENT
    // -------------------------------------------------------------------------
    public function openDispute(Request $request, Transaction $transaction)
    {
        if (!auth('sanctum')->user()?->can('openDispute', $transaction)) return abort(403);

        $request->validate(['reason' => 'required|string|max:1000']);

        $transaction->update([
            'dispute_status'    => 'OPEN',
            'dispute_opened_at' => now(),
            'dispute_reason'    => $request->reason,
        ]);

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => auth('sanctum')->id(),
            'action'           => 'dispute_opened',
            'reason'           => $request->reason,
            'metadata'         => ['ip' => request()->ip()],
        ]);

        // Notify admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new DisputeOpened($transaction, auth('sanctum')->user(), $request->reason));
        }

        return ['transaction' => $transaction->fresh()];
    }

    public function resolveDispute(Request $request, Transaction $transaction)
    {
        $request->validate([
            'outcome' => 'required|in:RESOLVED,LOST',
            'reason'  => 'required|string|max:1000',
        ]);

        $transaction->update([
            'dispute_status'      => $request->outcome,
            'dispute_resolved_at' => now(),
        ]);

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => auth('sanctum')->id(),
            'action'           => 'dispute_resolved',
            'old_value'        => 'OPEN',
            'new_value'        => $request->outcome,
            'reason'           => $request->reason,
            'metadata'         => ['ip' => request()->ip()],
        ]);

        $transaction->user->notify(new DisputeResolved($transaction, $request->outcome, $request->reason));

        return ['transaction' => $transaction->fresh()];
    }

    public function requestRefund(RefundRequestStoreRequest $request, Transaction $transaction)
    {
        $refundRequest = RefundRequest::create([
            'uuid'              => Str::uuid()->toString(),
            'user_id'           => auth('sanctum')->id(),
            'transaction_uuid'  => $transaction->uuid,
            'order_uuid' => $transaction->order_uuid,
            'amount'            => $request->amount ?? $transaction->amount,
            'reason'            => $request->reason,
            'status'            => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get(); // adjust role check as needed
        foreach ($admins as $admin) {
            $admin->notify(new RefundRequested($refundRequest, $transaction, auth('sanctum')->user()));
        }

        return response()->json(['refund_request' => $refundRequest], 201);
    }

    public function cancelDispute(Request $request, Transaction $transaction)
    {
        if (!auth('sanctum')->user()->can('cancelDispute', $transaction)) return abort(403);

        $transaction->update([
            'dispute_status'    => null,
            'dispute_opened_at' => null,
            'dispute_reason'    => null,
        ]);

        TransactionAuditLog::create([
            'transaction_uuid' => $transaction->uuid,
            'performed_by'     => auth('sanctum')->id(),
            'action'           => 'dispute_cancelled',
            'reason'           => 'Customer cancelled',
            'metadata'         => ['ip' => request()->ip()],
        ]);

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new DisputeCancelled($transaction, auth('sanctum')->user()));
        }

        return ['transaction' => $transaction->fresh()];
    }
}
