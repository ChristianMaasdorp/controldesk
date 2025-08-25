<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class GenerateTicketMarkdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticket:generate-markdown {ticket_id? : The ID of the ticket to generate markdown for} {--all : Generate markdown for all tickets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate markdown documentation for tickets using OpenAI';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ticketId = $this->argument('ticket_id');
        $all = $this->option('all');

        if ($all) {
            $this->generateForAllTickets();
        } elseif ($ticketId) {
            $this->generateForTicket($ticketId);
        } else {
            $this->error('Please provide a ticket ID or use --all option');
            return 1;
        }

        return 0;
    }

    /**
     * Generate markdown for a specific ticket
     *
     * @param int $ticketId
     */
    protected function generateForTicket($ticketId)
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            $this->error("Ticket with ID {$ticketId} not found");
            return;
        }

        $this->info("Generating markdown for ticket: {$ticket->code} - {$ticket->name}");

        try {
            $markdown = $ticket->generateMarkdownContent();

            if ($markdown) {
                $this->info("✅ Markdown generated successfully!");
                $this->line("Preview:");
                $this->line(substr($markdown, 0, 200) . "...");
            } else {
                $this->error("❌ Failed to generate markdown");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Generate markdown for all tickets
     */
    protected function generateForAllTickets()
    {
        $tickets = Ticket::all();
        $bar = $this->output->createProgressBar($tickets->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($tickets as $ticket) {
            try {
                $markdown = $ticket->generateMarkdownContent();
                if ($markdown) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Generated markdown for {$successCount} tickets");

        if ($errorCount > 0) {
            $this->warn("⚠️  Failed to generate markdown for {$errorCount} tickets");
        }
    }
}
