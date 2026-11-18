<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiChatConversationRequest extends FormRequest
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
            'source' => ['nullable', 'string', 'in:widget,full-screen'],
            'section_id' => ['nullable', 'string', 'exists:sections,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.exists' => '指定された教材が見つかりません。',
            'message.max' => '最初の質問は2000文字以内で入力してください。',
        ];
    }
}
