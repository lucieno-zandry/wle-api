<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->convertCurrency();

        return array_merge(parent::toArray($request), [
            'can' => [
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ]);
    }
}
