<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

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
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'information' => ['nullable', 'string'],
            'hourly_rate' => ['required', 'integer', 'min:0'],
            'need_approval' => ['required', 'boolean'],
            'is_open_access' => ['required', 'boolean'],
        ];
    }
}