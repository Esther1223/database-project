<?php

namespace App\Http\Requests\Approval;

use App\Models\Approval;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class DecisionApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Adjust authorization logic as needed (e.g. check roles/permissions)
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'decision' => ['required', 'string', 'in:'.Approval::DECISION_APPROVED.','.Approval::DECISION_REJECTED],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.in' => 'The decision must be either "approved" or "rejected".',
            'reservation_id.exists' => 'The selected reservation does not exist.',
        ];
    }
}
