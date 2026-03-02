<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        if (! auth()->check() || auth()->user()->role->value !== $role) {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول.',
            ], 403);
        }

        return $next($request);
    }
}
