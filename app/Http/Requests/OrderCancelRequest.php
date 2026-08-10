<?php

namespace App\Http\Requests;

use App\Rules\CancellableOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Override;

class OrderCancelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Log::debug($this->order);
        Log::debug("this order -----");

        return $this->user()->can('cancel', $this->order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', new CancellableOrder],
            'reason' => 'nullable|string|max:255'
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'order' => $this->order,
        ]);
    }
}
