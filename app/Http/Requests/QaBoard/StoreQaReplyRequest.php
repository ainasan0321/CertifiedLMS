<?php

namespace App\Http\Requests\QaBoard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQaReplyRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => '回答の入力は必須です。',
            'body.string' => '回答は文字列で入力してください。',
            'body.max' => '回答は5000文字以内で入力してください。',
        ];
    }

    protected function prepareForValidation(): void
    {
        $body  = $this->input('body') ?? '';

        $this->merge([
            'body'  => preg_replace('/^[[:space:]]+|[[:space:]]+$/u', '', $body),
        ]);
    }
}
