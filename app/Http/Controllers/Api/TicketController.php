<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * Store a newly created ticket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
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
        ]);

        // Create a new ticket
        $ticket = Ticket::create($validated);

        return response()->json([
            'message' => 'Ticket created successfully.',
            'ticket' => $ticket
        ], 201);
    }

}
