<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paymentStatus' => ['sometimes', Rule::in(['waiting', 'paid', 'rejected'])],
            'orderStatus' => ['sometimes', Rule::in(['preorder', 'confirmed', 'cancelled'])],
            'productionStatus' => ['sometimes', Rule::in(['waiting', 'queued', 'in_production', 'ready'])],
            'deliveryStatus' => ['sometimes', Rule::in(['waiting', 'ready_for_pickup', 'shipped', 'delivered'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
