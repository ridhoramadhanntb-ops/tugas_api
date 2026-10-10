<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware autentikasi: membaca "Authorization: Bearer <token>",
 * mencocokkan hash-nya dengan users.api_token, lalu menetapkan user yang login.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $user = $token
            ? User::where('api_token', hash('sha256', $token))->first()
            : null;

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated. Token tidak ada atau tidak valid.'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
