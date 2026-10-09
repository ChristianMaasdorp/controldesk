<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketWriteApiTest extends TestCase
{
    use RefreshDatabase;

    private const READ_TOKEN = 'test-read-token';
    private const WRITE_TOKEN = 'test-write-token';

    private User $serviceUser;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->serviceUser = User::factory()->create(['name' => 'Pipeline']);
        config([
            'services.tickets_api.token' => self::READ_TOKEN,
            'services.tickets_api.write_token' => self::WRITE_TOKEN,
            'services.tickets_api.user_id' => $this->serviceUser->id,
        ]);
    }

    public function test_write_endpoints_require_the_write_token(): void
    {
        $ticket = $this->createTicket();

        $this->postJson('/api/tickets', [])->assertUnauthorized();
        $this->patchJson('/api/tickets/'.$ticket->id, [])->assertUnauthorized();
        $this->postJson('/api/tickets/'.$ticket->id.'/comments', [])->assertUnauthorized();

        // The read token must not grant write access.
        $this->withToken(self::READ_TOKEN)->patchJson('/api/tickets/'.$ticket->id, [])->assertUnauthorized();
        $this->withToken(self::READ_TOKEN)->postJson('/api/tickets', [])->assertUnauthorized();
    }

    public function test_write_token_is_rejected_when_not_configured(): void
    {
        config(['services.tickets_api.write_token' => '']);

        $this->withToken('')->postJson('/api/tickets', [])->assertUnauthorized();
    }

    public function test_write_fails_clearly_when_service_user_is_missing(): void
    {
        config(['services.tickets_api.user_id' => null]);
        $ticket = $this->createTicket();

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $ticket->status_id])
            ->assertStatus(500);
    }

    public function test_creating_a_ticket_validates_input(): void
    {
        $this->withToken(self::WRITE_TOKEN)
            ->postJson('/api/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'name', 'owner_id', 'status_id', 'type_id', 'priority_id', 'content']);
    }

    public function test_it_updates_the_status_by_id_and_records_activity_as_the_service_user(): void
    {
        $ticket = $this->createTicket();
        $done = $this->createStatus($ticket->project_id, 'Done');

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $done->id])
            ->assertOk()
            ->assertJsonPath('ticket.status.name', 'Done');

        $this->assertSame($done->id, $ticket->fresh()->status_id);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'old_status_id' => $ticket->status_id,
            'new_status_id' => $done->id,
            'user_id' => $this->serviceUser->id,
        ]);
    }

    public function test_it_updates_the_status_by_ticket_code(): void
    {
        $ticket = $this->createTicket();
        $done = $this->createStatus($ticket->project_id, 'Done');

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->code, ['status_id' => $done->id])
            ->assertOk();

        $this->assertSame($done->id, $ticket->fresh()->status_id);
    }

    public function test_it_rejects_a_status_from_another_project_and_other_fields(): void
    {
        $ticket = $this->createTicket();
        $other = $this->createTicket();

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $other->status_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);

        $done = $this->createStatus($ticket->project_id, 'Done');
        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $done->id, 'name' => 'Renamed'])
            ->assertOk();

        $this->assertSame('Default ticket', $ticket->fresh()->name);
    }

    public function test_it_accepts_a_global_status_and_lists_it_for_any_project(): void
    {
        $ticket = $this->createTicket();
        $global = TicketStatus::create([
            'name' => 'Global Done',
            'color' => '#008000',
            'is_default' => false,
            'order' => 9,
            'project_id' => null,
        ]);

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $global->id])
            ->assertOk()
            ->assertJsonPath('ticket.status.name', 'Global Done');

        $this->withToken(self::READ_TOKEN)
            ->getJson('/api/ticket-statuses?project_id='.$ticket->project_id)
            ->assertOk()
            ->assertJsonFragment(['id' => $global->id]);
    }

    public function test_status_update_returns_not_found_for_unknown_ticket(): void
    {
        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/999999', ['status_id' => 1])
            ->assertNotFound();
    }

    public function test_status_change_is_silent_by_default_and_notifies_when_opted_in(): void
    {
        $ticket = $this->createTicket();
        $watcher = User::factory()->create();
        $ticket->project->users()->attach($watcher->id, ['role' => 'member']);
        $done = $this->createStatus($ticket->project_id, 'Done');
        $review = $this->createStatus($ticket->project_id, 'Review');
        Notification::fake(); // discard notifications from setup

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $done->id])
            ->assertOk();
        Notification::assertNothingSent();

        $this->withToken(self::WRITE_TOKEN)
            ->patchJson('/api/tickets/'.$ticket->id, ['status_id' => $review->id, 'notify' => true])
            ->assertOk();
        Notification::assertSentTo($watcher, \App\Notifications\TicketStatusUpdated::class);
    }

    public function test_it_adds_a_comment_attributed_to_the_service_user(): void
    {
        $ticket = $this->createTicket();

        $this->withToken(self::WRITE_TOKEN)
            ->postJson('/api/tickets/'.$ticket->code.'/comments', ['content' => 'Verified invalid on cmsdemo.'])
            ->assertCreated()
            ->assertJsonPath('comment.content', 'Verified invalid on cmsdemo.')
            ->assertJsonPath('comment.user.name', 'Pipeline');

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->serviceUser->id,
        ]);
    }

    public function test_comment_requires_content(): void
    {
        $ticket = $this->createTicket();

        $this->withToken(self::WRITE_TOKEN)
            ->postJson('/api/tickets/'.$ticket->id.'/comments', ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_it_lists_statuses_filtered_by_project(): void
    {
        $ticket = $this->createTicket();
        $this->createTicket();

        $this->getJson('/api/ticket-statuses')->assertUnauthorized();

        $this->withToken(self::READ_TOKEN)
            ->getJson('/api/ticket-statuses?project_id='.$ticket->project_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticket->status_id);
    }

    private function createStatus(int $projectId, string $name): TicketStatus
    {
        return TicketStatus::create([
            'name' => $name,
            'color' => '#cecece',
            'is_default' => false,
            'order' => 2,
            'project_id' => $projectId,
        ]);
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
            'ticket_prefix' => 'T'.strtoupper(substr(uniqid('', true), -6)),
        ]);
        $status = $this->createStatus($project->id, 'Open');
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
