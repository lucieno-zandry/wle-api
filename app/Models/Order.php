<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Services\CurrencyService;
use App\Traits\ApplyFilters;
use App\Traits\CustomerFilterable;
use App\Traits\DynamicConditionApplicable;
use App\Traits\HasEffectivePrice;
use App\Traits\WithOrdering;
use App\Traits\WithPagination;
use App\Traits\WithRelationships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use CustomerFilterable, ApplyFilters, SoftDeletes, HasEffectivePrice;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'address_snapshot' => 'array',
        'coupon_snapshot'  => 'array',
        'shipping_method_snapshot' => 'array',
        'status' => OrderStatus::class,
    ];

    protected $fillable = [
        'address_id',
        'notes'
    ];

    public function has_no_successful_payment()
    {
        $successful_transaction = Transaction::where('order_uuid', $this->uuid)->first();
        return !$successful_transaction;
    }

    public function cart_items()
    {
        return $this->hasMany(CartItem::class, 'order_uuid', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'order_uuid');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function refund_requests()
    {
        return $this->hasMany(RefundRequest::class, 'order_uuid');
    }

    public function convertCurrency()
    {
        $this->setValuesToConvertedCurrency([
            'total' => $this->total,
            'coupon_discount_applied' => $this->coupon_discount_applied,
            'shipping_cost' => $this->shipping_cost,
        ]);

        if ($this->coupon_snapshot) {
            $this->coupon_snapshot = (new Coupon)->convertSnapshotCurrency($this->coupon_snapshot);
        }

        if ($this->relationLoaded('coupon')) {
            $this->coupon?->convertCurrency();
        }

        if ($this->relationLoaded('transactions')) {
            /** @var \App\Models\Transaction */
            foreach ($this->transactions as $transaction) {
                $transaction->convertCurrency();
            }
        }

        if ($this->relationLoaded('cart_items')) {
            /** @var \App\Models\CartItem */
            foreach ($this->cart_items as $cart_item) {
                $cart_item->convertCurrency();
            }
        }
    }

    public function isCancellable(?User $user = null): bool
    {
        $disallowedStatuses = [
            OrderStatus::SHIPPED->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::CANCELLED->value,
            OrderStatus::COMPLETED->value,
        ];

        if ($user && !$user->roleIsAdmin()) {
            $disallowedStatuses[] = OrderStatus::PROCESSING->value;
        }

        return !in_array($this->status->value, $disallowedStatuses, true);
    }
}
