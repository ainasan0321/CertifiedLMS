<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class EnrollmentGoalStoreRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:100'],
            'target_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは100文字以内で入力してください。',
            'target_date.date' => '目標期日は日付を入力してください。',
            'description.max' => '詳細は1000文字以内で入力してください。'
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title') ?? '';

        $this->merge([
            'title' => preg_replace('/^[[:space:]]+|[[:space:]]+$/u', '', $title),
        ]);
    }
}
