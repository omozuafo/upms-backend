<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserSession;
use Illuminate\Support\Facades\Auth;

class EnsureActiveSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = auth('api')->payload();
            $sessionKey = $payload->get('session_key');

            if (!$sessionKey) {
                Auth::logout();
                return response()->json(['message' => 'Invalid session.'], 401);
            }

            $session = UserSession::where('session_key', $sessionKey)
                ->where('is_active', true)
                ->first();

            if (!$session) {
                Auth::logout();
                return response()->json(['message' => 'Session expired or invalidated.'], 401);
            }

            // Optional: Update last activity or expiry here

        } catch (\Exception $e) {
            return response()->json(['message' => 'Token invalid.'], 401);
        }

        return $next($request);
    }
}
