<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;


class TransactionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return $user->roleIsAdmin() || $user->id === $transaction->user_id;
    }

    /**
     * Determine whether the user cancel this dispute.
     */
    public function cancelDispute(User $user, Transaction $transaction)
    {
        return $user->id === $transaction->user_id
            && $transaction->dispute_status === 'OPEN';
    }

    /**
     * Determin whether the user can request a refund
     */
    public function requestRefund(User $user, Transaction $transaction)
    {
        return $user->id === $transaction->user_id
            && $transaction->status === TransactionStatus::SUCCESS->value
            && $transaction->type === 'PAYMENT'
            && !$transaction->has_pending_refund_requests();
    }

    // app/Policies/TransactionPolicy.php
    public function openDispute(User $user, Transaction $transaction)
    {
        return $user->id === $transaction->user_id
            && $transaction->status === TransactionStatus::SUCCESS->value
            && $transaction->dispute_status !== 'OPEN';
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
