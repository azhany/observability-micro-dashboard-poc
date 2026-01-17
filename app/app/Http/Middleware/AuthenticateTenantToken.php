<?php

namespace App\Http\Middleware;

use App\Models\TenantToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract Bearer token from Authorization header
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Hash the token and verify against the database
        $hashedToken = hash('sha256', $token);

        $tenantToken = TenantToken::where('token', $hashedToken)
            ->with('tenant')
            ->first();

        if (! $tenantToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Update last_used_at timestamp at most once per minute to reduce DB load
        if ($tenantToken->last_used_at === null || $tenantToken->last_used_at->diffInMinutes(now()) >= 1) {
            $tenantToken->update(['last_used_at' => now()]);
        }

        // Bind the tenant to the request context
        $request->attributes->set('tenant', $tenantToken->tenant);

        return $next($request);
    }
}
