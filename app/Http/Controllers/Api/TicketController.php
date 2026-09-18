<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;

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

    /**
     * List tickets with a lean payload for browsing.
     */
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $query = Ticket::query()
            ->with([
                'project:id,name',
                'status:id,name',
                'type:id,name',
                'priority:id,name',
                'owner:id,name',
                'responsible:id,name',
            ]);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->input('status_id'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $tickets = $query->orderByDesc('updated_at')->paginate($perPage);

        $tickets->getCollection()->transform(fn (Ticket $ticket) => $this->ticketSummary($ticket));

        return response()->json($tickets);
    }

    /**
     * Retrieve a ticket by numeric id or ticket code, including comments.
     */
    public function get($id)
    {
        $ticket = Ticket::query()
            ->with([
                'project:id,name',
                'status:id,name',
                'type:id,name',
                'priority:id,name',
                'owner:id,name',
                'responsible:id,name',
                'comments' => function ($query) {
                    $query->orderBy('created_at', 'desc')->with('user:id,name');
                },
            ])
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('code', $id);
            })
            ->firstOrFail();

        return response()->json([
            'ticket' => $this->ticketDetail($ticket),
        ]);
    }

    private function ticketSummary(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'name' => $ticket->name,
            'project' => $this->namedRelation($ticket->project),
            'status' => $this->namedRelation($ticket->status),
            'type' => $this->namedRelation($ticket->type),
            'priority' => $this->namedRelation($ticket->priority),
            'owner' => $this->namedRelation($ticket->owner),
            'responsible' => $this->namedRelation($ticket->responsible),
            'updated_at' => $ticket->updated_at,
        ];
    }

    private function ticketDetail(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'name' => $ticket->name,
            'content' => $ticket->content,
            'markdown_content' => $ticket->markdown_content,
            'branch' => $ticket->branch,
            'project' => $this->namedRelation($ticket->project),
            'status' => $this->namedRelation($ticket->status),
            'type' => $this->namedRelation($ticket->type),
            'priority' => $this->namedRelation($ticket->priority),
            'owner' => $this->namedRelation($ticket->owner),
            'responsible' => $this->namedRelation($ticket->responsible),
            'comments' => $ticket->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                    'user' => $this->namedRelation($comment->user),
                ];
            })->values(),
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }

    private function namedRelation($model): ?array
    {
        if (! $model) {
            return null;
        }

        return [
            'id' => $model->id,
            'name' => $model->name,
        ];
    }
}
