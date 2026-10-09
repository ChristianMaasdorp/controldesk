<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketReadApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-tickets-token';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['services.tickets_api.token' => self::TOKEN]);
    }

    public function test_read_endpoints_require_a_token(): void
    {
        $this->getJson('/api/tickets')->assertUnauthorized();
        $this->getJson('/api/tickets/1')->assertUnauthorized();
    }

    public function test_read_endpoints_reject_a_wrong_token(): void
    {
        $this->withToken('wrong-token')
            ->getJson('/api/tickets')
            ->assertUnauthorized();
    }

    public function test_read_endpoints_reject_requests_when_token_is_not_configured(): void
    {
        config(['services.tickets_api.token' => '']);

        $this->withToken(self::TOKEN)
            ->getJson('/api/tickets')
            ->assertUnauthorized();
    }

    public function test_it_lists_tickets_and_filters_by_query(): void
    {
        $matching = $this->createTicket(['name' => 'Broken login form']);
        $this->createTicket(['name' => 'Unrelated billing issue']);

        $response = $this->withToken(self::TOKEN)
            ->getJson('/api/tickets?q=login')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.name', 'Broken login form')
            ->assertJsonPath('data.0.code', $matching->code)
            ->assertJsonPath('data.0.project.name', $matching->project->name)
            ->assertJsonPath('data.0.status.name', $matching->status->name);

        $this->assertArrayNotHasKey('content', $response->json('data.0'));
        $this->assertArrayNotHasKey('comments', $response->json('data.0'));
    }

    public function test_it_shows_a_ticket_by_id_including_content_and_comments(): void
    {
        $ticket = $this->createTicket([
            'name' => 'Fix checkout',
            'content' => '<p>Checkout is broken</p>',
            'markdown_content' => 'Checkout is broken',
        ]);
        $commenter = User::factory()->create(['name' => 'Ada Lovelace']);
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $commenter->id,
            'content' => 'Reproduced on staging.',
        ]);

        $this->withToken(self::TOKEN)
            ->getJson('/api/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonPath('ticket.code', $ticket->code)
            ->assertJsonPath('ticket.name', 'Fix checkout')
            ->assertJsonPath('ticket.content', '<p>Checkout is broken</p>')
            ->assertJsonPath('ticket.markdown_content', 'Checkout is broken')
            ->assertJsonCount(1, 'ticket.comments')
            ->assertJsonPath('ticket.comments.0.content', 'Reproduced on staging.')
            ->assertJsonPath('ticket.comments.0.user.name', 'Ada Lovelace');
    }

    public function test_it_shows_a_ticket_by_code(): void
    {
        $ticket = $this->createTicket(['name' => 'Lookup by code']);

        $this->withToken(self::TOKEN)
            ->getJson('/api/tickets/'.$ticket->code)
            ->assertOk()
            ->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonPath('ticket.code', $ticket->code);
    }

    public function test_it_returns_not_found_for_an_unknown_ticket(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/tickets/999999')
            ->assertNotFound();
    }

    public function test_creating_a_ticket_does_not_require_the_read_token(): void
    {
        $this->postJson('/api/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'name', 'owner_id', 'status_id', 'type_id', 'priority_id', 'content']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicket(array $overrides = []): Ticket
    {
        $owner = User::factory()->create();
        $projectStatus = ProjectStatus::create([
            'name' => 'Active',
            'color' => '#00aa00',
            'is_default' => false,
        ]);
        $project = Project::create([
            'name' => 'Control Desk',
            'description' => 'Test project',
            'owner_id' => $owner->id,
            'status_id' => $projectStatus->id,
            'ticket_prefix' => 'T'.strtoupper(substr(uniqid(), -5)),
        ]);
        $status = TicketStatus::create([
            'name' => 'Open',
            'color' => '#cecece',
            'is_default' => false,
            'order' => 1,
            'project_id' => $project->id,
        ]);
        $type = TicketType::create([
            'name' => 'Bug',
            'icon' => 'heroicon-o-bug-ant',
            'color' => '#ff0000',
            'is_default' => false,
        ]);
        $priority = TicketPriority::create([
            'name' => 'High',
            'color' => '#ff0000',
            'is_default' => false,
        ]);

        return Ticket::create(array_merge([
            'name' => 'Default ticket',
            'content' => '<p>Default content</p>',
            'owner_id' => $owner->id,
            'status_id' => $status->id,
            'project_id' => $project->id,
            'type_id' => $type->id,
            'priority_id' => $priority->id,
        ], $overrides));
    }
}
