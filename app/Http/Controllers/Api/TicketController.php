<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;

class TicketController extends Controller
{
    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request)
    {
        // Create a new ticket
        $ticket = Ticket::create($request->validated());
        return response()->json([
            'message' => 'Ticket created successfully.',
            'ticket' => $ticket
        ], 201);
    }

    public function get($id)
    {
        // Retrieve the ticket by ID
        $ticket = Ticket::findOrFail($id);
        return response()->json([
            'ticket' => $ticket
        ]);
    }
}
