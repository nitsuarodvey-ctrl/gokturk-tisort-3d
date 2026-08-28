<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartPaymentRequest extends FormRequest
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
            'orderId' => ['required', 'uuid', 'exists:orders,id'],
            'cardHolderName' => ['required', 'string', 'between:2,45'],
            'cardNumber' => ['required', 'string', 'regex:/^\d{13,19}$/'],
            'expiryMonth' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])$/'],
            'expiryYear' => ['required', 'string', 'regex:/^\d{2}$/'],
            'cvv' => ['required', 'string', 'regex:/^\d{3}$/'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'billingCity' => ['required', 'string', 'max:80'],
            'billingState' => ['required', 'string', 'regex:/^\d{1,3}$/'],
            'billingPostalCode' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'billingAddress' => ['required', 'string', 'max:250'],
            'clientIp' => ['required', 'ipv4'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('cardNumber') && ! $this->passesLuhn((string) $this->input('cardNumber'))) {
                    $validator->errors()->add('cardNumber', 'Kart numarası geçersiz.');
                }

                if ($validator->errors()->hasAny(['expiryMonth', 'expiryYear'])) {
                    return;
                }

                $expiryYear = 2000 + (int) $this->input('expiryYear');
                $expiryMonth = (int) $this->input('expiryMonth');
                if ($expiryYear < (int) now()->format('Y') || ($expiryYear === (int) now()->format('Y') && $expiryMonth < (int) now()->format('n'))) {
                    $validator->errors()->add('expiryYear', 'Kartın son kullanma tarihi geçmiş.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cardHolderName' => trim((string) $this->input('cardHolderName')),
            'cardNumber' => preg_replace('/\D+/', '', (string) $this->input('cardNumber')),
            'expiryMonth' => str_pad(preg_replace('/\D+/', '', (string) $this->input('expiryMonth')), 2, '0', STR_PAD_LEFT),
            'expiryYear' => substr(preg_replace('/\D+/', '', (string) $this->input('expiryYear')), -2),
            'cvv' => preg_replace('/\D+/', '', (string) $this->input('cvv')),
            'billingState' => preg_replace('/\D+/', '', (string) $this->input('billingState')),
            'clientIp' => (string) $this->header('X-Client-IP'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cardNumber.regex' => 'Kart numarası geçersiz.',
            'expiryMonth.regex' => 'Son kullanma ayı geçersiz.',
            'expiryYear.regex' => 'Son kullanma yılı geçersiz.',
            'cvv.regex' => 'CVV üç haneli olmalıdır.',
            'billingState.regex' => 'İl kodu geçersiz.',
            'clientIp.ipv4' => 'Ödeme için geçerli bir IPv4 adresi alınamadı.',
        ];
    }

    private function passesLuhn(string $cardNumber): bool
    {
        $sum = 0;
        $alternate = false;

        for ($index = strlen($cardNumber) - 1; $index >= 0; $index--) {
            $digit = (int) $cardNumber[$index];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
