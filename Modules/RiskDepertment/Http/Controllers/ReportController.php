<?php

namespace Modules\RiskDepertment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Modules\Authentication\Models\Country;
use Modules\RiskDepertment\Models\IncidentReport;
use Modules\RiskDepertment\Models\IncidentType;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */



    public function getIncidentByType(Request $request)
    {
        $site = app('site');

        // ✅ inputs (optional)
        $weeks = (int) $request->input('weeks', 8);
        if ($weeks < 1) $weeks = 1;
        if ($weeks > 52) $weeks = 52;

        $tz = $request->input('tz', config('app.timezone', 'UTC'));

        // ✅ date window (includes current day, so current week is "to date")
        $end = Carbon::now($tz)->endOfDay();
        $start = Carbon::now($tz)->subWeeks($weeks - 1)->startOfWeek(Carbon::MONDAY)->startOfDay();

        // ✅ Explicit week buckets (ALWAYS include current week)
        $weekStarts = [];
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        $endWeek = $end->copy()->startOfWeek(Carbon::MONDAY);

        while ($cursor->lte($endWeek)) {
            $weekStarts[] = $cursor->format('Y-m-d');
            $cursor->addWeek();
        }

        $currentWeekStart = Carbon::now($tz)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

        // ✅ statuses
        $closedStatuses = ['closed', 'resolved'];
        $newStatuses = ['submitted', 'under_review', 'investigating'];

        // ✅ base query (created window)
        $baseCreated = IncidentReport::query()
            ->where('site_id', $site->id)
            ->whereBetween('created_at', [$start, $end]);

        // ✅ SAFE week key: never returns NULL
        $weekKeyCreated = "DATE_FORMAT(DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(DATE(created_at)) DAY), '%Y-%m-%d')";
        $weekKeyUpdated = "DATE_FORMAT(DATE_SUB(DATE(updated_at), INTERVAL WEEKDAY(DATE(updated_at)) DAY), '%Y-%m-%d')";

        // =========================
        // 1) Weekly incidents by type (created)
        // =========================
        $weeklyByTypeRaw = (clone $baseCreated)
            ->selectRaw("$weekKeyCreated as week_start")
            ->selectRaw("COALESCE(incident_type, 'Unknown') as incident_type")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('week_start', 'incident_type')
            ->orderBy('week_start')
            ->get();

        // =========================
        // 2) Totals by type (pie)
        // =========================
        $totalsByType = (clone $baseCreated)
            ->selectRaw("COALESCE(incident_type, 'Unknown') as incident_type")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('incident_type')
            ->orderByDesc('total')
            ->get();

        $totalIncidents = (clone $baseCreated)->count();

        $shares = $totalsByType->map(function ($row) use ($totalIncidents) {
            $count = (int) $row->total;
            $pct = $totalIncidents > 0 ? round(($count / $totalIncidents) * 100, 2) : 0;
            return [
                'incident_type' => $row->incident_type,
                'count' => $count,
                'share_percent' => $pct,
            ];
        })->values();

        // =========================
        // 3) KPIs (within created window)
        // =========================
        $closedCount = (clone $baseCreated)->whereIn('status', $closedStatuses)->count();
        $newCount = (clone $baseCreated)->whereIn('status', $newStatuses)->count();
        $openCount = (clone $baseCreated)->whereNotIn('status', $closedStatuses)->count();

        // =========================
        // 4) Weekly totals (created)
        // =========================
        $weeklyTotalsRaw = (clone $baseCreated)
            ->selectRaw("$weekKeyCreated as week_start")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();

        // =========================
        // 5) Weekly closed (FIXED) — use updated_at window
        // =========================
        $weeklyClosedRaw = IncidentReport::query()
            ->where('site_id', $site->id)
            ->whereIn('status', $closedStatuses)
            ->whereBetween('updated_at', [$start, $end]) // closure activity happens later
            ->selectRaw("$weekKeyUpdated as week_start")
            ->selectRaw("COUNT(*) as closed")
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();

        // ✅ Zero-fill weekly totals/closed to include current week
        $weeklyTotalsMap = $weeklyTotalsRaw->keyBy('week_start');
        $weeklyClosedMap = $weeklyClosedRaw->keyBy('week_start');

        $weeklyTotals = collect($weekStarts)->map(function ($w) use ($weeklyTotalsMap) {
            return ['week_start' => $w, 'total' => (int) ($weeklyTotalsMap[$w]->total ?? 0)];
        })->values();

        $weeklyClosed = collect($weekStarts)->map(function ($w) use ($weeklyClosedMap) {
            return ['week_start' => $w, 'closed' => (int) ($weeklyClosedMap[$w]->closed ?? 0)];
        })->values();

        // =========================
        // 6) Top types
        // =========================
        $topTypes = $totalsByType->take(5)->values()->map(fn ($r) => [
            'incident_type' => $r->incident_type,
            'count' => (int) $r->total,
        ]);

        // =========================
        // 7) Country analytics (FULL)
        // =========================

        // 7a) Totals by country (created window)
        $totalsByCountry = (clone $baseCreated)
            ->selectRaw("country_id")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('country_id')
            ->orderByDesc('total')
            ->get();

        $countryIds = $totalsByCountry->pluck('country_id')->filter()->unique()->values()->all();

        $countryMap = [];
        if (!empty($countryIds)) {
            $countryMap = Country::whereIn('id', $countryIds)->pluck('name', 'id')->toArray();
        }

        $countryShare = $totalsByCountry->map(function ($row) use ($countryMap, $totalIncidents) {
            $count = (int) $row->total;
            $pct = $totalIncidents > 0 ? round(($count / $totalIncidents) * 100, 2) : 0;

            return [
                'country_id' => $row->country_id,
                'country' => $countryMap[$row->country_id] ?? 'Unknown',
                'count' => $count,
                'share_percent' => $pct,
            ];
        })->values();

        $topCountry = $countryShare->first();

        // 7b) Weekly by country (created window) + zero-fill per country/week
        $weeklyByCountryRaw = (clone $baseCreated)
            ->selectRaw("$weekKeyCreated as week_start")
            ->selectRaw("country_id")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('week_start', 'country_id')
            ->orderBy('week_start')
            ->get();

        $weeklyByCountry = collect();
        foreach ($countryIds as $cid) {
            $map = $weeklyByCountryRaw->where('country_id', $cid)->keyBy('week_start');

            foreach ($weekStarts as $w) {
                $weeklyByCountry->push([
                    'week_start' => $w,
                    'country_id' => $cid,
                    'country' => $countryMap[$cid] ?? 'Unknown',
                    'total' => (int) ($map[$w]->total ?? 0),
                ]);
            }
        }

        // 7c) Country × Type matrix (created window) — THIS is what you want for stacked bars
        $countryTypeMatrixRaw = (clone $baseCreated)
            ->selectRaw("country_id")
            ->selectRaw("COALESCE(incident_type, 'Unknown') as incident_type")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('country_id', 'incident_type')
            ->orderByDesc('total')
            ->get();

        $countryTypeMatrix = $countryTypeMatrixRaw->map(function ($row) use ($countryMap) {
            return [
                'country_id' => $row->country_id,
                'country' => $countryMap[$row->country_id] ?? 'Unknown',
                'incident_type' => $row->incident_type,
                'count' => (int) $row->total,
            ];
        })->values();

        // =========================
        // ✅ Response (chart-friendly)
        // =========================
        return response()->json([
            'meta' => [
                'site_id' => $site->id,
                'weeks' => $weeks,
                'timezone' => $tz,
                'range' => [
                    'start' => $start->toDateTimeString(),
                    'end' => $end->toDateTimeString(),
                ],
                'week_buckets' => $weekStarts,
                'current_week_start' => $currentWeekStart,
                'status_groups' => [
                    'closed' => $closedStatuses,
                    'new' => $newStatuses,
                ],
            ],

            'kpis' => [
                'total_incidents' => $totalIncidents,
                'new_incidents' => $newCount,
                'closed_incidents' => $closedCount,
                'open_incidents' => $openCount,
            ],

            'incident_share' => $shares,
            'weekly_by_type' => $weeklyByTypeRaw,

            // ✅ now correct + includes current week
            'weekly_totals' => $weeklyTotals,
            'weekly_closed' => $weeklyClosed,

            'top_types' => $topTypes,

            // ✅ countries
            'country_share' => $countryShare,
            'top_country' => $topCountry,
            'weekly_by_country' => $weeklyByCountry->values(),
            'country_type_matrix' => $countryTypeMatrix,
        ], 200);
    }








    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('riskdepertment::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('riskdepertment::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('riskdepertment::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
