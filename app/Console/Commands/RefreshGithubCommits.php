<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\GithubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshGithubCommits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'github:refresh-commits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh GitHub commits for all tickets with non-null branch fields';

    /**
     * The GitHub service instance.
     *
     * @var GithubService
     */
    protected GithubService $githubService;

    /**
     * Create a new command instance.
     *
     * @param GithubService $githubService
     * @return void
     */
    public function __construct(GithubService $githubService)
    {
        parent::__construct();
        $this->githubService = $githubService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting GitHub commits refresh...');

        $tickets = Ticket::whereNotNull('branch')
            ->whereHas('project', function ($query) {
                $query->whereNotNull('github_repository_url')
                    ->whereNotNull('github_api_key');
            })
            ->get();
        $count = $tickets->count();

        $this->info("Found {$count} tickets with branch information and configured GitHub repositories.");

        $successCount = 0;
        $errorCount = 0;

        foreach ($tickets as $ticket) {
            $this->info("Processing ticket #{$ticket->id} with branch: {$ticket->branch}");

            try {
                $this->info("Fetching commits for ticket #{$ticket->id} with URL: {$ticket->project->github_repository_url}");
                // Fetch commits from GitHub
                $commits = $this->githubService->getCommitsForBranch($ticket->branch, $ticket->project);

                // Store commits in the database
                foreach ($commits as $commit) {
                    $ticket->githubCommits()->updateOrCreate(
                        ['sha' => $commit['sha']],
                        [
                            'author' => $commit['author'],
                            'message' => $commit['message'],
                            'committed_at' => $commit['date'],
                            'branch' => $ticket->branch,
                        ]
                    );
                }

                $this->info("Successfully updated {$ticket->id} with " . count($commits) . " commits.");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Failed to fetch GitHub commits for ticket {$ticket->id} branch '{$ticket->branch}': " . $e->getMessage());
                Log::error("Failed to fetch GitHub commits for ticket {$ticket->id} branch '{$ticket->branch}': " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->info("GitHub commits refresh completed.");
        $this->info("Successfully processed: {$successCount} tickets");
        $this->info("Failed to process: {$errorCount} tickets");

        return $errorCount === 0 ? 0 : 1;
    }
}
