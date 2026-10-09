<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

    /**
     * List ticket statuses (statuses are per project) so API clients can
     * resolve the status_id to send on update.
     */
    public function statuses(Request $request)
    {
        $statuses = TicketStatus::query()
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->orderBy('project_id')
            ->orderBy('order')
            ->get(['id', 'name', 'project_id', 'order']);

        return response()->json(['data' => $statuses]);
    }

    /**
     * Change a ticket's status. Only the status can be changed through the API.
     */
    public function update(Request $request, $id)
    {
        $ticket = $this->findTicket($id);

        $data = $request->validate([
            'status_id' => [
                'required',
                'integer',
                Rule::exists('ticket_statuses', 'id')->where('project_id', $ticket->project_id),
            ],
        ]);

        $ticket->update($data);

        return response()->json([
            'message' => 'Ticket updated successfully.',
            'ticket' => $this->ticketSummary($ticket->fresh(['project:id,name', 'status:id,name', 'type:id,name', 'priority:id,name', 'owner:id,name', 'responsible:id,name'])),
        ]);
    }

    /**
     * Add a comment to a ticket, attributed to the API service user.
     */
    public function addComment(Request $request, $id)
    {
        $ticket = $this->findTicket($id);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:65535'],
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'content' => $data['content'],
        ]);

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user' => $this->namedRelation($comment->user),
            ],
        ], 201);
    }

    private function findTicket($id): Ticket
    {
        return Ticket::query()
            ->where(function ($query) use ($id) {
                $query->where('id', $id)->orWhere('code', $id);
            })
            ->firstOrFail();
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
