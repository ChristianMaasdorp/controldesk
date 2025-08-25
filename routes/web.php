<?php

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Support\Facades\Route;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Controllers\RoadMap\DataController;
use App\Http\Controllers\Auth\OidcAuthController;

// Share ticket
Route::get('/tickets/share/{ticket:code}', function (Ticket $ticket) {
    return redirect()->to(route('filament.resources.tickets.view', $ticket));
})->name('filament.resources.tickets.share');

// Validate an account
Route::get('/validate-account/{user:creation_token}', function (User $user) {
    return view('validate-account', compact('user'));
})
    ->name('validate-account')
    ->middleware([
        'web',
        DispatchServingFilamentEvent::class
    ]);

// Login default redirection
Route::redirect('/login-redirect', '/login')->name('login');

// Road map JSON data
Route::get('road-map/data/{project}', [DataController::class, 'data'])
    ->middleware(['verified', 'auth'])
    ->name('road-map.data');

Route::name('oidc.')
    ->prefix('oidc')
    ->group(function () {
        Route::get('redirect', [OidcAuthController::class, 'redirect'])->name('redirect');
        Route::get('callback', [OidcAuthController::class, 'callback'])->name('callback');
    });

Route::get('/ticket/note/{note}/read', function ($note) {
    $note = \App\Models\TicketNote::findOrFail($note);

    // Only allow marking as read if user is the intended recipient or responsible
    if (auth()->user()->id === $note->intended_for_id ||
        auth()->user()->id === $note->ticket->responsible_id) {
        $note->markAsRead();
        return redirect()->route('filament.resources.tickets.view', $note->ticket);
    }

    return abort(403);
})->middleware(['auth'])->name('ticket.note.read');

// Debug route for OpenAI service
Route::get('/debug/openai/{ticket_id}', function ($ticketId) {
    try {
        $ticket = \App\Models\Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $openAIService = app(\App\Services\OpenAIService::class);
        $prompt = $openAIService->buildTicketPrompt($ticket);

        // This will trigger the debug dump
        $openAIService->generateTicketMarkdown($ticket);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('debug.openai');

// Simple test route without relationships
Route::get('/debug/simple/{ticket_id}', function ($ticketId) {
    try {
        $ticket = \App\Models\Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        // Simple test without any relationships
        $debugData = [
            'ticket_id' => $ticket->id,
            'ticket_code' => $ticket->code,
            'ticket_name' => $ticket->name,
            'content' => $ticket->content,
            'markdown_content' => $ticket->markdown_content,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'basic_info' => 'This is a simple test without relationships'
        ];

        dd($debugData);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('debug.simple');

// Test markdown generation route
Route::get('/test/markdown/{ticket_id}', function ($ticket_id) {
    try {
        $ticket = \App\Models\Ticket::find($ticket_id);
        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }
        
        $openAIService = app(\App\Services\OpenAIService::class);
        
        if (!$openAIService->isConfigured()) {
            return response()->json(['error' => 'OpenAI API key is not configured'], 500);
        }
        
        $result = $openAIService->generateTicketMarkdown($ticket);
        
        if ($result) {
            return response()->json([
                'success' => true,
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->code,
                'ticket_name' => $ticket->name,
                'markdown_generated' => !empty($ticket->markdown_content),
                'markdown_length' => strlen($ticket->markdown_content ?? ''),
                'markdown_preview' => substr($ticket->markdown_content ?? '', 0, 500) . '...'
            ]);
        } else {
            return response()->json(['error' => 'Failed to generate markdown'], 500);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
})->name('test.markdown');
