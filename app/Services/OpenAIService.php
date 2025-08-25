<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    /**
     * Generate markdown documentation for a ticket
     *
     * @param Ticket $ticket
     * @return string|null
     */
    public function generateTicketMarkdown(Ticket $ticket)
    {
        try {
            $prompt = $this->buildTicketPrompt($ticket);

            // Debug: Dump the complete data that will be sent to OpenAI
            // Debug: Only dump the data if there's an error (comment out for production)
            // $this->debugOpenAIData($ticket, $prompt);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a technical documentation expert. Create comprehensive markdown documentation for software development tickets. Focus on clarity, completeness, and technical accuracy.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');

                // Update the ticket with the generated markdown
                $ticket->update(['markdown_content' => $content]);

                return $content;
            } else {
                Log::error('OpenAI API error', [
                    'ticket_id' => $ticket->id,
                    'response' => $response->json(),
                    'status' => $response->status()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('OpenAI service error', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build the prompt for the ticket
     *
     * @param Ticket $ticket
     * @return string
     */
    public function buildTicketPrompt(Ticket $ticket)
    {
        // Simple version without using exportToArray to isolate the issue
        $prompt = "Please create comprehensive markdown documentation for the following software development ticket:\n\n";
        $prompt .= "# Ticket Information\n\n";
        $prompt .= "**Code:** {$ticket->code}\n";
        $prompt .= "**Name:** {$ticket->name}\n";
        $prompt .= "**Status:** {$ticket->status->name}\n";
        $prompt .= "**Priority:** {$ticket->priority->name}\n";
        $prompt .= "**Type:** {$ticket->type->name}\n";
        $prompt .= "**Project:** {$ticket->project->name}\n";

        if ($ticket->epic) {
            $prompt .= "**Epic:** {$ticket->epic->name} ({$ticket->epic->starts_at} - {$ticket->epic->ends_at})\n";
        }

        if ($ticket->sprint) {
            $prompt .= "**Sprint:** {$ticket->sprint->name}\n";
        }

        $prompt .= "**Owner:** {$ticket->owner->name}\n";

        if ($ticket->responsible) {
            $prompt .= "**Assigned To:** {$ticket->responsible->name}\n";
        }

        $prompt .= "**Estimation:** {$ticket->estimation_for_humans}\n";
        $prompt .= "**Time Logged:** {$ticket->total_logged_hours}\n";

        if ($ticket->content) {
            $prompt .= "\n## Description\n\n{$ticket->content}\n";
        }

        // Add attached files information
        $media = $ticket->getMedia();
        if ($media->count() > 0) {
            $prompt .= "\n## Attached Files\n\n";
            foreach ($media as $file) {
                $prompt .= "- **{$file->name}** ({$file->mime_type}) - {$file->size} bytes\n";
                $prompt .= "  - File ID: {$file->id}\n";
                $prompt .= "  - Uploaded: {$file->created_at}\n";

                // Try to read file content for text-based files
                if (in_array($file->mime_type, ['text/plain', 'text/markdown', 'text/html', 'application/json', 'application/xml'])) {
                    try {
                        $fileContent = $file->getStream()->getContents();
                        $prompt .= "  - Content:\n```\n{$fileContent}\n```\n";
                    } catch (\Exception $e) {
                        $prompt .= "  - Content: Unable to read file content\n";
                    }
                }
                $prompt .= "\n";
            }
        }

        // Enhanced comments section with full content
        $comments = $ticket->comments()->with('user')->orderBy('created_at', 'asc')->get();
        if ($comments->count() > 0) {
            $prompt .= "\n## Comments and Discussion\n\n";
            foreach ($comments as $comment) {
                $prompt .= "### Comment by {$comment->user->name} on {$comment->created_at->format('Y-m-d H:i:s')}\n\n";
                $prompt .= "{$comment->content}\n\n";
                $prompt .= "---\n\n";
            }
        }

        // Add time logs
        $hours = $ticket->hours()->orderBy('created_at', 'asc')->get();
        if ($hours->count() > 0) {
            $prompt .= "\n## Time Logs\n\n";
            foreach ($hours as $hour) {
                $prompt .= "- **{$hour->created_at->format('Y-m-d H:i:s')}**: {$hour->value}h - {$hour->comment}\n";
            }
        }

        // Add activity history
        $activities = $ticket->activities()->with(['oldStatus', 'newStatus'])->orderBy('created_at', 'asc')->get();
        if ($activities->count() > 0) {
            $prompt .= "\n## Activity History\n\n";
            foreach ($activities as $activity) {
                $prompt .= "- Status changed from {$activity->oldStatus->name} to {$activity->newStatus->name} on {$activity->created_at}\n";
            }
        }

        $prompt .= "\n\nPlease create a well-structured markdown document that includes:\n";
        $prompt .= "1. A clear overview of the ticket\n";
        $prompt .= "2. Technical requirements and specifications\n";
        $prompt .= "3. Implementation details and considerations\n";
        $prompt .= "4. Testing requirements\n";
        $prompt .= "5. Any relevant notes or context\n";
        $prompt .= "6. Progress tracking information\n";
        $prompt .= "\nMake it professional, comprehensive, and easy to understand for developers and stakeholders.";

        return $prompt;
    }

    /**
     * Check if OpenAI is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /**
     * Debug method to dump the complete data that will be sent to OpenAI
     *
     * @param Ticket $ticket
     * @param string $prompt
     * @return void
     */
    protected function debugOpenAIData(Ticket $ticket, string $prompt)
    {
        try {
            $debugData = [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->code,
                'ticket_name' => $ticket->name,
                'prompt_length' => strlen($prompt),
                'prompt_preview' => substr($prompt, 0, 500) . '...',
                'full_prompt' => $prompt,
                'attached_files_count' => $ticket->getMedia()->count(),
                'comments_count' => $ticket->comments()->count(),
                'hours_count' => $ticket->hours()->count(),
                'activities_count' => $ticket->activities()->count(),
                'api_config' => [
                    'model' => 'gpt-4',
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                    'api_key_configured' => $this->isConfigured(),
                    'api_key_length' => strlen($this->apiKey ?? ''),
                ]
            ];

            // Try to get ticket data, but don't fail if database is not available
            try {
                $debugData['ticket_data'] = $ticket->exportToArray([$ticket->id])[0];
            } catch (\Exception $e) {
                $debugData['ticket_data_error'] = $e->getMessage();
            }

            // Dump the complete debug data
            dd($debugData);
        } catch (\Exception $e) {
            dd([
                'debug_error' => $e->getMessage(),
                'ticket_id' => $ticket->id ?? 'unknown',
                'prompt_length' => strlen($prompt),
                'prompt_preview' => substr($prompt, 0, 200) . '...'
            ]);
        }
    }
}
