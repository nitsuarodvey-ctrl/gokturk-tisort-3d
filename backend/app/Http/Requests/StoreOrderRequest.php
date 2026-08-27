<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^(?:\D*\d){10,15}\D*$/'],
            'size' => ['required', Rule::in(['S', 'M', 'L', 'XL'])],
            'quantity' => ['required', 'integer', 'between:1,20'],
            'deliveryType' => ['required', Rule::in(['Genel Merkezden Teslim', 'İzmir Elden Teslim', 'Adrese Kargo'])],
            'city' => ['nullable', 'string', 'max:80', 'required_if:deliveryType,Adrese Kargo'],
            'district' => ['nullable', 'string', 'max:80', 'required_if:deliveryType,Adrese Kargo'],
            'address' => ['nullable', 'string', 'max:500', 'required_if:deliveryType,Adrese Kargo'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Telefon numarası geçersiz.',
            'city.required_if' => 'Kargo teslimatı için şehir zorunludur.',
            'district.required_if' => 'Kargo teslimatı için ilçe zorunludur.',
            'address.required_if' => 'Kargo teslimatı için adres zorunludur.',
        ];
    }
}
