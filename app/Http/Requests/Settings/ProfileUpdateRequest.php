<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
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
        $user = $this->user();

        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        // ログインユーザーがコーチの場合のみ固定面談URLの検証を追加
        if ($user && $user->role === UserRole::Coach) {
            $rules['meeting_url'] = ['required', 'url', 'max:500'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => '名前は必須です。',
            'name.max' => '名前は50文字以内で入力してください。',
            'bio.max' => '自己紹介は1000文字以内で入力してください.',
            // コーチ用
            'meeting_url.required' => '固定面談 URLは必須です。',
            'meeting_url.url' => '正しいURL形式で入力してください。',
        ];
    }
}
