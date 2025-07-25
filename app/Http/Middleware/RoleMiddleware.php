<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle($request, Closure $next, ...$roles)
    {
        $user = auth()->user();

        if (!$user || !in_array($user->role->name, $roles)) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'You are not authorized to access this resource.'
            ], 403);
        }

        return $next($request);
    }

}
