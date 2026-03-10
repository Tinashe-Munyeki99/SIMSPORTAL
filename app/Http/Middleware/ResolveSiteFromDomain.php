<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\School\Models\School;
use Modules\Site\Models\Site;
use Symfony\Component\HttpFoundation\Response;

class ResolveSiteFromDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        // Prefer explicit header if present
        $rawHost = $request->header('X-Site-Domain')?? $request->getHost();

        // Strip port if present (e.g. localhost:5173 → localhost)
        $host = $rawHost;
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        \Log::info("ResolveSchoolFromDomain HIT. Raw: {$rawHost}, Using: {$host}");

        $site = Site::where('domain', $host)
            ->orWhere('host_ip', $host)
            ->first();

        if (!$site) {
            return response()->json(['message' => 'Unknown site'], 404);
        }

        app()->instance('site', $site);

        return $next($request);
    }
}
