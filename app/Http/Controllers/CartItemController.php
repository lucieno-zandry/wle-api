<?php

namespace App\Http\Controllers;

use App\Helpers\CartItemHelpers;
use App\Helpers\OrderHelpers;
use App\Http\Requests\CartItemCreateRequest;
use App\Http\Requests\CartItemDeleteRequest;
use App\Http\Requests\CartItemUpdateRequest;
use App\Models\CartItem;
use App\Models\Variant;
use App\Services\CartItemService;

class CartItemController extends Controller
{
    public function store(CartItemCreateRequest $request, int $variantId, CartItemService $service)
    {
        $data = $request->validated();
        $cartItem = $service->createCartItem($data, $variantId);

        return response()->json([
            'cart_item' => $cartItem
        ], $cartItem->exists ? 200 : 201);
    }

    public function update(CartItemUpdateRequest $request, CartItem $cartItem)
    {
        $data = $request->validated();

        $data['variant_id'] = $cartItem->variant_id;

        if (!isset($data['count'])) {
            $data['count'] = $cartItem->count;
        }

        // Reload variant with relations for accurate price calculation
        $variant = Variant::with(['product.images', 'variant_options.variant_group', 'image'])
            ->find($cartItem->variant_id);

        CartItemHelpers::make_item(
            $cartItem,
            $data,
            $variant
        );

        // Refresh the order if this item belongs to one
        if ($cartItem->order) {
            OrderHelpers::refresh_order($cartItem->order);
        }

        return [
            'cart_item' => $cartItem
        ];
    }

    public function destroy(CartItemDeleteRequest $request)
    {
        $cartItemIds = explode(',', $request->cart_item_ids);

        $deleted = CartItem::whereIn('id', $cartItemIds)
            ->where('user_id', auth('sanctum')->id())
            ->whereNull('order_uuid')
            ->delete();

        return [
            'deleted' => $deleted
        ];
    }

    public function show(int $cartItemId)
    {
        $cartItem = CartItem::withRelations()->find($cartItemId)?->convertCurrency();

        return [
            'cart_item' => $cartItem
        ];
    }

    public function index()
    {
        $cartItems = CartItem::applyFilters()
            ->where('user_id', auth('sanctum')->id())
            ->notOrdered()
            ->get()
            ->map(fn($cartItem) => $cartItem->convertCurrency());

        return [
            'cart_items' => $cartItems
        ];
    }
}
