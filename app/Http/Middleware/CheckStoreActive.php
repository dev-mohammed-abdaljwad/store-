<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStoreActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
     if ($user->isStoreOwner() && ! $user->store?->isActive()) {
            
            $user->currentAccessToken()->delete();

            return response()->json([
                'message' => 'تم إيقاف المتجر. يرجى التواصل مع الإدارة.',
            ], 403);
        }

        return $next($request);
    }
}
