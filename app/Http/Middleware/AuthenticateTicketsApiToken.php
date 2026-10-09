<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateTicketsApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $scope = 'read')
    {
        $write = $scope === 'write';
        $expected = (string) config($write ? 'services.tickets_api.write_token' : 'services.tickets_api.token');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($write) {
            // Writes are attributed to a dedicated service user so the model
            // hooks (ticket activity) have an acting user, and the token's
            // changes are identifiable in the audit trail.
            $user = User::find((int) config('services.tickets_api.user_id'));

            if (! $user) {
                return response()->json([
                    'message' => 'Tickets API service user is not configured.',
                ], 500);
            }

            Auth::setUser($user);

            // Pipeline writes are silent unless the caller opts in with notify=true.
            if (! $request->boolean('notify')) {
                $request->attributes->set('tickets.api.silent', true);
            }
        }

        return $next($request);
    }
}
