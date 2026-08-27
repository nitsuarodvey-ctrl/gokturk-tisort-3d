<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'size' => $this->size,
            'quantity' => $this->quantity,
            'deliveryType' => $this->delivery_type,
            'city' => $this->city ?? '',
            'district' => $this->district ?? '',
            'address' => $this->address ?? '',
            'unitPrice' => $this->unit_price,
            'total' => $this->total,
            'paymentStatus' => $this->payment_status,
            'orderStatus' => $this->order_status,
            'productionStatus' => $this->production_status,
            'deliveryStatus' => $this->delivery_status,
            'notes' => $this->notes ?? '',
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
