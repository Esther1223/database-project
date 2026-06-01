<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoomRequest extends FormRequest
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
            'afflication_id' => ['required', 'integer', 'exists:afflications,id'],
            'information' => ['nullable', 'string'],
            'hourly_rate' => ['required', 'integer', 'min:0'],
            'need_approval' => ['required', 'boolean'],
            'is_open_access' => ['required', 'boolean'],
            'open_access_all' => ['required', 'boolean'],
            'open_access_afflications' => [
                Rule::excludeUnless(fn () => $this->boolean('is_open_access') && ! $this->boolean('open_access_all')),
                'array',
                'min:1',
            ],
            'open_access_afflications.*' => ['integer', 'exists:afflications,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'afflication_id.required' => '請選擇空間的所屬單位。',
            'open_access_afflications.min' => '當啟用白名單開放時，請至少選擇一個可借用單位，或勾選所有人可借用。',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->isNotEmpty()
                || ! $this->boolean('is_open_access')
                || $this->boolean('open_access_all')
            ) {
                return;
            }

            $afflicationId = (int) $this->input('afflication_id');
            $openAfflicationIds = collect($this->input('open_access_afflications', []))
                ->map(fn ($id): int => (int) $id);

            if ($openAfflicationIds->contains($afflicationId)) {
                $validator->errors()->add('open_access_afflications', '白名單不可包含空間的所屬單位。');
            }
        });
    }
}
