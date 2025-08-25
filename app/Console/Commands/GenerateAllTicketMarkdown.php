<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateAllTicketMarkdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:generate-all-markdown {--limit= : Limit the number of tickets to process} {--dry-run : Show what would be processed without actually generating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate markdown documentation for all tickets that do not have markdown content';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $openAIService = app(OpenAIService::class);

        if (!$openAIService->isConfigured()) {
            $this->error('OpenAI API key is not configured. Please add OPENAI_API_KEY to your .env file.');
            return 1;
        }

        // Get tickets without markdown content
        $query = Ticket::whereNull('markdown_content')
            ->orWhere('markdown_content', '')
            ->orderBy('created_at', 'desc');

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            $this->info('No tickets found without markdown content.');
            return 0;
        }

        $this->info("Found {$tickets->count()} tickets without markdown content.");

        if ($this->option('dry-run')) {
            $this->info('DRY RUN - No markdown will be generated.');
            $this->newLine();

            $this->table(
                ['ID', 'Code', 'Name', 'Status', 'Project'],
                $tickets->map(function ($ticket) {
                    return [
                        $ticket->id,
                        $ticket->code,
                        $ticket->name,
                        $ticket->status,
                        $ticket->project->name ?? 'N/A'
                    ];
                })->toArray()
            );

            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm("Generate markdown for {$tickets->count()} tickets? This may take a while and use OpenAI API credits.")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($tickets->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($tickets as $ticket) {
            try {
                $this->info("\nProcessing ticket: {$ticket->code} - {$ticket->name}");

                $result = $openAIService->generateTicketMarkdown($ticket);

                if ($result) {
                    $successCount++;
                    $this->info("✅ Generated markdown for {$ticket->code}");
                } else {
                    $errorCount++;
                    $errors[] = "Failed to generate markdown for {$ticket->code} (ID: {$ticket->id})";
                    $this->error("❌ Failed to generate markdown for {$ticket->code}");
                }

                // Add a small delay to avoid rate limiting
                sleep(1);

            } catch (\Exception $e) {
                $errorCount++;
                $errorMessage = "Error generating markdown for {$ticket->code} (ID: {$ticket->id}): " . $e->getMessage();
                $errors[] = $errorMessage;

                Log::error($errorMessage);
                $this->error("❌ {$errorMessage}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("🎉 Markdown generation completed!");
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Tickets', $tickets->count()],
                ['Successfully Generated', $successCount],
                ['Errors', $errorCount],
            ]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->warn('Errors encountered:');
            foreach ($errors as $error) {
                $this->line("• {$error}");
            }
        }

        return $errorCount > 0 ? 1 : 0;
    }
}
