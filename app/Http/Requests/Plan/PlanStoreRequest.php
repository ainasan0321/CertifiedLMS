<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'between:1,3650'],
            'default_meeting_quota' => ['required', 'integer', 'between:0,1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'プラン名は必須です。',
            'name.max' => 'プラン名は200文字以内で入力してください。',
            'description.max' => '説明は2000文字以内で入力してください。',
            'duration_days.required' => '受講期間(日)は必須です。',
            'duration_days.integer' => '受講期間(日)は整数で入力してください。',
            'duration_days.between' => '受講期間(日)は1〜3650日の間で指定してください。',
            'default_meeting_quota.required' => '初期付与面談回数は必須です。',
            'default_meeting_quota.integer' => '初期付与面談回数は整数で入力してください。',
            'default_meeting_quota.between' => '初期付与面談回数は0〜1000回の間で指定してください。',
            'sort_order.integer' => '並び順は整数で入力してください。',
            'sort_order.min' => '並び順は0以上の数値を入力してください。',
        ];
    }
}
