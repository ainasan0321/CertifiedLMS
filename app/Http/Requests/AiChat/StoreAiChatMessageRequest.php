<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiChatMessageRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => '質問内容を入力してください。',
            'content.max' => '質問内容は2000文字以内で入力してください。',
            'content.regex' => '質問内容を入力してください。',
        ];
    }
}
