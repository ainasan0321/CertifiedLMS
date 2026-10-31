<?php

namespace App\Http\Requests\MeetingPack;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingPackRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'パック名は必須入力です。',
            'name.max' => 'パック名は100文字以内で入力してください。',
            
            'meeting_count.required' => '面談回数は必須入力です。',
            'meeting_count.integer' => '面談回数は整数で入力してください。',
            'meeting_count.min' => '面談回数は1回以上で入力してください。',
            
            'price.required' => '価格は必須入力です。',
            'price.integer' => '価格は整数で入力してください。',
            'price.min' => '価格は0円以上で入力してください。',
            'price.max' => '価格は1,000,000円以下で入力してください。',
            
            'description.max' => '説明は1000文字以内で入力してください。',
            'stripe_price_id.max' => 'Stripe Price IDは255文字以内で入力してください。',
            'sort_order.integer' => '並び順は整数で入力してください。',
            'sort_order.min' => '並び順は0以上の数値を入力してください。',
        ];
    }

}
