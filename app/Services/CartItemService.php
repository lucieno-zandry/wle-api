<?php

namespace App\Services;

use App\Helpers\CartItemHelpers;
use App\Models\CartItem;
use App\Models\Variant;

class CartItemService
{
    public function createCartItem(array $data, int $variantId, ?Variant $variant = null): CartItem
    {
        $userId = auth('sanctum')->id();

        // 1. Fetch existing item or instantiate a clean new instance
        $cartItem = CartItem::where('user_id', $userId)
            ->where('variant_id', $variantId)
            ->whereNull('order_uuid')
            ->first() ?? new CartItem();

        // 2. Pass to helper (ensure helper checks $cartItem->count + incoming count <= $variant->stock)
        $newItem = CartItemHelpers::make_item(
            $cartItem,
            array_merge($data, [
                'user_id' => $userId,
                'variant_id' => $variantId,
            ]),
            $variant
        );

        return $newItem;
    }
}
