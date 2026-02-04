<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserisVendor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof Vendor) {
            return response()->json([
                'message' => 'Access denied. This endpoint is only for vendors.',
                'error' => 'You are logged in as a user, but trying to access vendor endpoints. Please login with type=vendor.'
            ], 403);
        }

        return $next($request);
    }
}