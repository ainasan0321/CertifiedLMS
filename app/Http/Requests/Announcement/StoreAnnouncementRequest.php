<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAnnouncementRequest extends FormRequest
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
            'title' => 'required', 'string', 'max:200',
            'body' => 'required', 'string', 'max:5000',
            'target_type' => 'required', 'string', new Enum(AnnouncementTargetType::class),
            'target_certification_id' => 'required_if:target_type,'.AnnouncementTargetType::Certification->value, 'nullable', 'string',
            'target_user_id' => 'required_if:target_type,'.AnnouncementTargetType::User->value, 'nullable', 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは200文字以内で入力してください。',
            'body.required' => '本文は必須です。',
            'body.max' => '本文は5000文字以内で入力してください。',
            'target_type.required' => '配信対象の選択は必須です。',
            'target_certification_id.required_if' => '対象資格の選択は必須です。',
            'target_user_id.required_if' => '対象受講生の選択は必須です。',
        ];
    }
}
