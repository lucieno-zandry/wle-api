<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundRequestApproveRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user('sanctum')->roleIsAdmin();
    }

    public function rules()
    {
        return [
            'admin_notes' => 'nullable|string|max:1000',
            'transaction_reference' => 'nullable|string|max:100',
            'payment_method' => Rule::enum(PaymentMethod::class),
            
        ];
    }
}
