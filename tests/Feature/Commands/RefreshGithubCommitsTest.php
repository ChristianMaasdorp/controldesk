<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\RefreshGithubCommits;
use App\Models\Ticket;
use App\Services\GithubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RefreshGithubCommitsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var \Mockery\MockInterface
     */
    protected $mock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GitHub service
        $this->mock = Mockery::mock(GithubService::class);
        $this->app->instance(GithubService::class, $this->mock);
    }

    public function test_command_refreshes_commits_for_tickets_with_branches()
    {
        // Create a ticket with a branch
        $ticket = Ticket::factory()->create([
            'branch' => 'feature/test-branch'
        ]);

        // Mock the GitHub service to return some commits
        $this->mock->shouldReceive('getCommitsForBranch')
            ->once()
            ->with('feature/test-branch')
            ->andReturn([
                [
                    'sha' => 'abc123',
                    'author' => 'Test Author',
                    'message' => 'Test commit message',
                    'date' => '2023-01-01 12:00:00'
                ]
            ]);

        // Run the command
        $this->artisan('github:refresh-commits')
            ->expectsOutput('Starting GitHub commits refresh...')
            ->expectsOutput('Found 1 tickets with branch information.')
            ->expectsOutput("Processing ticket #{$ticket->id} with branch: feature/test-branch")
            ->expectsOutput("Successfully updated {$ticket->id} with 1 commits.")
            ->expectsOutput('GitHub commits refresh completed.')
            ->expectsOutput('Successfully processed: 1 tickets')
            ->expectsOutput('Failed to process: 0 tickets')
            ->assertExitCode(0);

        // Assert that the commit was stored in the database
        $this->assertDatabaseHas('github_commits', [
            'ticket_id' => $ticket->id,
            'sha' => 'abc123',
            'author' => 'Test Author',
            'message' => 'Test commit message',
            'branch' => 'feature/test-branch'
        ]);
    }

    public function test_command_handles_errors_gracefully()
    {
        // Create a ticket with a branch
        $ticket = Ticket::factory()->create([
            'branch' => 'feature/error-branch'
        ]);

        // Mock the GitHub service to throw an exception
        $this->mock->shouldReceive('getCommitsForBranch')
            ->once()
            ->with('feature/error-branch')
            ->andThrow(new \Exception('GitHub API error'));

        // Mock the Log facade
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Failed to fetch GitHub commits for ticket \d+ branch \'feature\/error-branch\': GitHub API error/'));

        // Run the command
        $this->artisan('github:refresh-commits')
            ->expectsOutput('Starting GitHub commits refresh...')
            ->expectsOutput('Found 1 tickets with branch information.')
            ->expectsOutput("Processing ticket #{$ticket->id} with branch: feature/error-branch")
            ->expectsOutput('GitHub commits refresh completed.')
            ->expectsOutput('Successfully processed: 0 tickets')
            ->expectsOutput('Failed to process: 1 tickets')
            ->assertExitCode(1);
    }

    public function test_command_skips_tickets_without_branches()
    {
        // Create a ticket without a branch
        Ticket::factory()->create([
            'branch' => null
        ]);

        // Create a ticket with a branch
        $ticket = Ticket::factory()->create([
            'branch' => 'feature/test-branch'
        ]);

        // Mock the GitHub service to return some commits
        $this->mock->shouldReceive('getCommitsForBranch')
            ->once()
            ->with('feature/test-branch')
            ->andReturn([
                [
                    'sha' => 'abc123',
                    'author' => 'Test Author',
                    'message' => 'Test commit message',
                    'date' => '2023-01-01 12:00:00'
                ]
            ]);

        // Run the command
        $this->artisan('github:refresh-commits')
            ->expectsOutput('Starting GitHub commits refresh...')
            ->expectsOutput('Found 1 tickets with branch information.')
            ->expectsOutput("Processing ticket #{$ticket->id} with branch: feature/test-branch")
            ->expectsOutput("Successfully updated {$ticket->id} with 1 commits.")
            ->expectsOutput('GitHub commits refresh completed.')
            ->expectsOutput('Successfully processed: 1 tickets')
            ->expectsOutput('Failed to process: 0 tickets')
            ->assertExitCode(0);
    }
}
