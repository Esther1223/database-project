<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRoomRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'building' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'information' => ['nullable', 'string'],
            'hourly_rate' => ['required', 'integer', 'min:0'],
            'need_approval' => ['required', 'boolean'],
            'is_open_access' => ['required', 'boolean'],
            'open_access_departments' => [
                Rule::excludeUnless(fn () => $this->boolean('is_open_access')),
                'array',
                'min:1',
            ],
            'open_access_departments.*' => ['integer', 'exists:departments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => '請選擇空間的所屬單位。',
            'open_access_departments.min' => '當啟用白名單開放時，請至少選擇一個可借用單位。',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_open_access')) {
                return;
            }

            $departmentId = (int) $this->input('department_id');
            $openDepartmentIds = collect($this->input('open_access_departments', []))
                ->map(fn ($id): int => (int) $id);

            if ($openDepartmentIds->contains($departmentId)) {
                $validator->errors()->add('open_access_departments', '白名單不可包含空間的所屬單位。');
            }
        });
    }
}
