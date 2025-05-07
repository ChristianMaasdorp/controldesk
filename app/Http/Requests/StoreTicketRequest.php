<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'project_id' => ['required', 'exists:projects,id'],
            'epic_id' => ['nullable', 'exists:epics,id'],
            'name' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', 'exists:users,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status_id' => ['required', 'exists:ticket_statuses,id'],
            'type_id' => ['required', 'exists:ticket_types,id'],
            'priority_id' => ['required', 'exists:ticket_priorities,id'],
            'content' => ['required', 'string'],
            'estimation' => ['nullable', 'numeric'],
        ];
    }
}
