<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->is_admin) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Admin access required.',
                ], 403);
            }
            
            return redirect()->route('dashboard')
                ->with('error', 'Admin access required.');
        }

        return $next($request);
    }
}