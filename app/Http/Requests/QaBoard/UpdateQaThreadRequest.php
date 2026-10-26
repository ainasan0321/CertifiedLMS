<?php

namespace App\Http\Requests\QaBoard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQaThreadRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは200文字以内で入力してください。',
            'body.required' => '本文は必須です。',
            'body.max' => '本文は5000文字以内で入力してください。',
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title') ?? '';
        $body  = $this->input('body') ?? '';

        $this->merge([
            'title' => preg_replace('/^[[:space:]]+|[[:space:]]+$/u', '', $title),
            'body'  => preg_replace('/^[[:space:]]+|[[:space:]]+$/u', '', $body),
        ]);
    }
}
