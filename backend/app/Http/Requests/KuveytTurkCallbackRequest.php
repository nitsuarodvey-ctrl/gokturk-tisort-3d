<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class KuveytTurkCallbackRequest extends FormRequest
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
            'AuthenticationResponse' => ['required', 'string', 'max:100000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $response = (string) $this->input('AuthenticationResponse');
        if (str_starts_with($response, '%3C') || str_starts_with($response, '%3c')) {
            $response = rawurldecode($response);
        }

        $this->merge(['AuthenticationResponse' => $response]);
    }
}
