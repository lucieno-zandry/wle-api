<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Helpers\CartItemHelpers;
use App\Helpers\OrderHelpers;
use App\Http\Requests\OrderCancelRequest;
use App\Http\Requests\OrderCheckoutRequest;
use App\Http\Requests\OrderCreateRequest;
use App\Http\Requests\OrderDeleteRequest;
use App\Http\Requests\OrderUpdateRequest;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Variant;
use App\Services\OrderCancellationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{

    public function store(OrderCreateRequest $request)
    {
        $data = $request->only(['address_id', 'coupon_id', 'shipping_method_id', 'notes']);

        // 1. Validate address ownership (No transaction needed)
        $address = Address::where('id', $data['address_id'])
            ->where('user_id', auth('sanctum')->id())
            ->firstOrFail();

        // 2. Fetch cart items to prepare for shipping calculation
        $cartItemsForShipping = CartItem::whereIn('id', $request->cart_item_ids)
            ->notOrdered()
            ->get();

        if ($cartItemsForShipping->isEmpty()) {
            abort(403, "These cart items have already been ordered.");
        }

        // 3. HEAVY SHIPPING CALCULATION (API Call) - Run this OUTSIDE the transaction!
        $shippingMethod = \App\Models\ShippingMethod::findOrFail($data['shipping_method_id']);
        $items = $cartItemsForShipping->map(fn($item) => [
            'weight_kg' => $item->variant_snapshot['weight_kg'] ?? 0,
            'quantity' => $item->count,
            'price' => $item->unit_price,
        ]);

        $calculator = app(\App\Services\ShippingCalculatorService::class);
        $calculator->setAddress($address)
            ->setItems($items)
            ->setMethod($shippingMethod);

        try {
            $shippingCost = $calculator->calculate();
        } catch (\Exception $e) {
            abort(422, "Selected shipping method is not available: " . $e->getMessage());
        }

        // 4. Start an ultra-fast transaction to secure the inventory and order
        return DB::transaction(function () use ($request, $data, $address, $shippingMethod, $shippingCost, $calculator) {

            // Re-verify that cart items haven't been ordered in the split second we did the API call
            $cartItems = CartItem::whereIn('id', $request->cart_item_ids)
                ->notOrdered()
                ->get();

            if ($cartItems->isEmpty()) {
                abort(403, "These cart items have already been ordered.");
            }

            $variantIds = $cartItems->pluck('variant_id')->unique()->filter();

            // Lock rows for update
            $variants = Variant::whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Verify available stock (Stock minus what is currently reserved)
            $stockVerified = $cartItems->every(function ($item) use ($variants) {
                $variant = $variants->get($item->variant_id);
                // Assuming you have a 'reserved_stock' column
                $availableStock = $variant->stock - $variant->reserved_stock;
                return $variant && $item->count <= $availableStock;
            });

            if (!$stockVerified) {
                abort(403, "Some of the products are out of stock!");
            }

            // PERSIST THE RESERVATION IMMEDIATELY while we hold the lock
            foreach ($cartItems as $item) {
                $variant = $variants->get($item->variant_id);
                $variant->increment('reserved_stock', $item->count);
            }

            // Save the order
            $order = new Order();
            $order->uuid = Str::uuid()->toString();

            $order = OrderHelpers::make_order($order, $cartItems, $data, $shippingCost, $shippingMethod);
            $order->address_snapshot = $address->snapshot();
            $order->shipping_method_id = $shippingMethod->id;
            $order->shipping_cost = $shippingCost;
            $order->total_weight_kg = $calculator->getTotalWeight();
            $order->shipping_method_snapshot = [
                'name' => $shippingMethod->name,
                'carrier' => $shippingMethod->carrier,
                'min_delivery_days' => $shippingMethod->min_delivery_days,
                'max_delivery_days' => $shippingMethod->max_delivery_days,
            ];

            $order->save();

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order
            ], 201);
        });
    }

    /**
     * Helper to release reserved stock if something fails prior to payment redirect
     */
    private function releaseReservedStock(Collection $cartItems)
    {
        foreach ($cartItems as $item) {
            DB::table('variants')
                ->where('id', $item->variant_id)
                ->decrement('reserved_stock', $item->count);
        }
    }

    public function update(OrderUpdateRequest $request, Order $order)
    {
        $data = $request->validated();

        $order->update($data);

        return [
            'order' => $order
        ];
    }

    public function destroy(OrderDeleteRequest $request)
    {
        $order_uuids = explode(',', $request->order_uuids);
        $deleted = Order::whereIn('uuid', $order_uuids)->delete();

        return [
            'deleted' => $deleted
        ];
    }

    public function index(Request $request)
    {
        $query = Order::withRelations();

        /** @var \App\Models\User */
        $user = Auth::user();

        if ($user?->can('viewAny', Order::class))
            $query = $query->withTrashed();

        // Apply customer filter (if role is customer)
        $query->customerFilterable();

        // Apply sorting from 'sort' parameter (e.g., 'created_at' or '-created_at')
        if ($request->has('sort')) {
            $sort = $request->sort;
            $direction = 'asc';
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $sort = substr($sort, 1);
            }
            $query->orderBy($sort, $direction);
        }

        // Apply search (if you need to implement it)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply payment status filter (you'll need to join/whereHas transactions)
        if ($request->has('payment_status') && !empty($request->payment_status)) {
            $query->whereHas('transactions', function ($tQuery) use ($request) {
                $tQuery->where('status', $request->payment_status);
            });
        }

        // Apply shipment status filter
        if ($request->has('shipment_status') && !empty($request->shipment_status)) {
            $query->whereHas('shipments', function ($sQuery) use ($request) {
                $sQuery->where('status', $request->shipment_status);
            });
        }

        if ($request->has('total_min')) {
            $query->where('total', '>=', $request->total_min);
        }

        if ($request->has('total_max')) {
            $query->where('total', '<=', $request->total_max);
        }

        // Paginate using standard Laravel pagination
        $perPage = $request->get('per_page', 20);
        $orders = $query->paginate($perPage);

        /** @var \App\Models\Order */
        foreach ($orders as $order) {
            $order->convertCurrency();
        }

        return response()->json($orders);
    }

    public function show(string $order_uuid)
    {
        /** @var \App\Models\Order | null*/
        $order = Order::withRelations()->where('uuid', $order_uuid)->first();

        if ($order?->has_no_successful_payment())
            $order = OrderHelpers::refresh_order($order);

        $order?->convertCurrency();

        return [
            'order' => $order
        ];
    }

    public function checkout(OrderCheckoutRequest $request)
    {
        $validated = $request->validated();

        $cartItemIds = $validated['cart_items_ids'] ?? [];

        if (!empty($validated['variants'])) {
            foreach ($validated['variants'] as $variantData) {
                $cartItem = CartItemHelpers::make_item(new CartItem, $variantData);
                $cartItemIds[] = $cartItem->id;
            }
        }

        // Remove duplicates just in case
        $cartItemIds = array_values(array_unique($cartItemIds));

        if (!empty($cartItemIds)) {
            $response = response()
                ->json(['success' => true])
                ->cookie('cart_items_ids', implode(',', $cartItemIds), 60);

            if (isset($validated['coupon_code'])) {
                $response = $response
                    ->cookie('coupon_code', $validated['coupon_code'], 60);
            } else {
                $response = $response
                    ->withoutCookie('coupon_code');
            }

            return $response;
        }

        return response()->json([
            'message' => 'Failed to initiate checkout.'
        ], 403);
    }

    public function cancel(OrderCancelRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Execute service logic
        app(OrderCancellationService::class)->cancelOrder($order, $validated['reason'] ?? 'Customer initiated cancellation');

        return response()->json([
            'message' => 'Order cancelled successfully. A refund request has been submitted.',
            'order_uuid' => $order->uuid
        ]);
    }
}
