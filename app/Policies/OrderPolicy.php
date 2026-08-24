<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->roleIsAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->roleIsAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->roleIsAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->roleIsAdmin();
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): Response
    {
        if ($order->user_id !== $user->id && !$user->roleIsAdmin()) {
            return Response::deny('You do not own this order.');
        }

        if (!$order->isCancellable($user)) {
            return Response::deny('This order is already being processed or cancelled.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->roleIsAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->roleIsAdmin();
    }
}
