<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Rules\UsableCoupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['exists:addresses,id'],
            'coupon_id' => ['nullable', 'exists:coupons,id', new UsableCoupon],
            'status' => [Rule::enum(OrderStatus::class)->except(OrderStatus::CANCELLED)]
        ];
    }
}
