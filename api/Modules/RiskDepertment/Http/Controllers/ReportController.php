<?php

namespace Modules\RiskDepertment\Http\Controllers;

use App\Http\Controllers\Controller;
use Beta\Microsoft\Graph\SecurityNamespace\Model\Department;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Modules\Authentication\Models\Brand;
use Modules\Authentication\Models\BrandOfficeManagement;
use Modules\Authentication\Models\Country;
use Modules\Authentication\Models\Office;
use Modules\RiskDepertment\Models\IncidentReport;
use Modules\RiskDepertment\Models\IncidentSlaEvent;
use Modules\RiskDepertment\Models\IncidentType;
use Modules\RolesAndPermissions\Models\Depertment;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */





    public function getIncidentByType(Request $request)
    {
        $site = app('site');
        $user = auth()->user();

        // ✅ inputs
        $weeks = (int) $request->input('weeks', 8);
        if ($weeks < 1) $weeks = 1;
        if ($weeks > 52) $weeks = 52;

        $tz = $request->input('tz', config('app.timezone', 'UTC'));

        // ✅ user role check
        $isSuperAdmin = (
            strtolower((string)($user->role->name ?? '')) === 'super admin'
            || (bool)($user->is_super_admin ?? false)
        );

        // ✅ date window
        $end = Carbon::now($tz)->endOfDay();
        $start = Carbon::now($tz)
            ->subWeeks($weeks - 1)
            ->startOfWeek(Carbon::MONDAY)
            ->startOfDay();

        // ✅ Explicit week buckets
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

        // ✅ get user brand / office mappings
        $brandIds = BrandOfficeManagement::where('user_id', $user->id)
            ->whereNotNull('brand_id')
            ->pluck('brand_id')
            ->unique()
            ->values()
            ->all();

        $officeIds = BrandOfficeManagement::where('user_id', $user->id)
            ->whereNotNull('office_id')
            ->pluck('office_id')
            ->unique()
            ->values()
            ->all();

        // ✅ user's country restriction
        $userCountryId = optional($user->otherInfo)->country;

        /**
         * ✅ visibility-scoped base query
         * Super Admin => all site incidents
         * Non-super admin => only incidents for mapped brands / offices
         * If user has a country assigned => restrict to that country
         * If country is null => multi-country viewer, no country restriction
         */
        $visibleBase = IncidentReport::query()
            ->where('site_id', $site->id);

        if (!$isSuperAdmin) {
            $visibleBase->where(function ($sub) use ($brandIds, $officeIds) {
                $hasAny = false;

                if (!empty($brandIds)) {
                    $sub->whereIn('brand_id', $brandIds);
                    $hasAny = true;
                }

                if (!empty($officeIds)) {
                    if ($hasAny) {
                        $sub->orWhereIn('brand_id', $officeIds); // ✅ use office_id if column exists
                    } else {
                        $sub->whereIn('brand_id', $officeIds);
                        $hasAny = true;
                    }
                }
            });

            // ✅ if user belongs to one country, restrict to that country
            if (!empty($userCountryId)) {
                $visibleBase->where('country_id', $userCountryId);
            }

            // ✅ no mappings => no data
            if (empty($brandIds) && empty($officeIds)) {
                $visibleBase->whereRaw('1=0');
            }
        }

        // ✅ base query limited by created window + visibility
        $baseCreated = (clone $visibleBase)
            ->whereBetween('created_at', [$start, $end]);

        // ✅ SAFE week keys
        $weekKeyCreated = "DATE_FORMAT(DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(DATE(created_at)) DAY), '%Y-%m-%d')";
        $weekKeyUpdated = "DATE_FORMAT(DATE_SUB(DATE(updated_at), INTERVAL WEEKDAY(DATE(updated_at)) DAY), '%Y-%m-%d')";

        // =========================
        // 1) Weekly incidents by type
        // =========================
        $weeklyByTypeRaw = (clone $baseCreated)
            ->selectRaw("$weekKeyCreated as week_start")
            ->selectRaw("COALESCE(incident_type, 'Unknown') as incident_type")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('week_start', 'incident_type')
            ->orderBy('week_start')
            ->get();

        // =========================
        // 2) Totals by type
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
        // 3) KPIs
        // =========================
        $closedCount = (clone $baseCreated)->whereIn('status', $closedStatuses)->count();
        $newCount = (clone $baseCreated)->whereIn('status', $newStatuses)->count();
        $openCount = (clone $baseCreated)->whereNotIn('status', $closedStatuses)->count();

        // =========================
        // 4) Weekly totals
        // =========================
        $weeklyTotalsRaw = (clone $baseCreated)
            ->selectRaw("$weekKeyCreated as week_start")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();

        // =========================
        // 5) Weekly closed (by updated_at)
        // =========================
        $weeklyClosedRaw = (clone $visibleBase)
            ->whereIn('status', $closedStatuses)
            ->whereBetween('updated_at', [$start, $end])
            ->selectRaw("$weekKeyUpdated as week_start")
            ->selectRaw("COUNT(*) as closed")
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get();

        $weeklyTotalsMap = $weeklyTotalsRaw->keyBy('week_start');
        $weeklyClosedMap = $weeklyClosedRaw->keyBy('week_start');

        $weeklyTotals = collect($weekStarts)->map(function ($w) use ($weeklyTotalsMap) {
            return [
                'week_start' => $w,
                'total' => (int) ($weeklyTotalsMap[$w]->total ?? 0),
            ];
        })->values();

        $weeklyClosed = collect($weekStarts)->map(function ($w) use ($weeklyClosedMap) {
            return [
                'week_start' => $w,
                'closed' => (int) ($weeklyClosedMap[$w]->closed ?? 0),
            ];
        })->values();

        // =========================
        // 6) Top types
        // =========================
        $topTypes = $totalsByType->take(5)->values()->map(fn ($r) => [
            'incident_type' => $r->incident_type,
            'count' => (int) $r->total,
        ]);

        // =========================
        // 7) Country analytics
        // =========================
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
        // 8) Brand analytics
        // =========================
        $totalsByBrand = (clone $baseCreated)
            ->selectRaw("brand_id")
            ->selectRaw("COUNT(*) as total")
            ->groupBy('brand_id')
            ->orderByDesc('total')
            ->get();

        $brandIdsFound = $totalsByBrand->pluck('brand_id')->filter()->unique()->values()->all();

        $brandMap = [];
        if (!empty($brandIdsFound)) {
            $brandMap = Brand::whereIn('id', $brandIdsFound)->pluck('name', 'id')->toArray();
        }

        $brandShare = $totalsByBrand->map(function ($row) use ($brandMap, $totalIncidents) {
            $count = (int) $row->total;
            $pct = $totalIncidents > 0 ? round(($count / $totalIncidents) * 100, 2) : 0;

            return [
                'brand_id' => $row->brand_id,
                'brand' => $brandMap[$row->brand_id] ?? 'Unknown',
                'count' => $count,
                'share_percent' => $pct,
            ];
        })->values();

        return response()->json([
            'meta' => [
                'site_id' => $site->id,
                'weeks' => $weeks,
                'timezone' => $tz,
                'is_super_admin' => $isSuperAdmin,
                'visible_brand_ids' => $isSuperAdmin ? [] : $brandIds,
                'visible_office_ids' => $isSuperAdmin ? [] : $officeIds,
                'viewer_country_id' => $isSuperAdmin ? null : $userCountryId,
                'is_multi_country_viewer' => !$isSuperAdmin && empty($userCountryId),
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
            'weekly_totals' => $weeklyTotals,
            'weekly_closed' => $weeklyClosed,
            'top_types' => $topTypes,

            'country_share' => $countryShare,
            'top_country' => $topCountry,
            'weekly_by_country' => $weeklyByCountry->values(),
            'country_type_matrix' => $countryTypeMatrix,

            'brand_share' => $brandShare,
        ], 200);
    }

    public function getFinancialLoss(Request $request)
    {
        $site = app('site');
        $user = auth()->user();

        $tz = $request->input('tz', config('app.timezone', 'UTC'));

        $isSuperAdmin = (
            strtolower((string)($user->role->name ?? '')) === 'super admin'
            || (bool)($user->is_super_admin ?? false)
        );

        // ✅ MTD range
        $start = Carbon::now($tz)->startOfMonth()->startOfDay();
        $end   = Carbon::now($tz)->endOfDay();

        // ✅ mappings
        $brandIds = BrandOfficeManagement::where('user_id', $user->id)
            ->whereNotNull('brand_id')
            ->pluck('brand_id')
            ->unique()
            ->values()
            ->all();

        $officeIds = BrandOfficeManagement::where('user_id', $user->id)
            ->whereNotNull('office_id')
            ->pluck('office_id')
            ->unique()
            ->values()
            ->all();

        $userCountryId = optional($user->otherInfo)->country;

        // ✅ base query
        $baseQuery = IncidentReport::query()
            ->where('site_id', $site->id)
            ->whereBetween('incident_at', [$start, $end]);

        if (!$isSuperAdmin) {
            $baseQuery->where(function ($sub) use ($brandIds, $officeIds) {
                $hasAny = false;

                if (!empty($brandIds)) {
                    $sub->whereIn('brand_id', $brandIds);
                    $hasAny = true;
                }

                if (!empty($officeIds)) {
                    if ($hasAny) {
                        $sub->orWhereIn('brand_id', $officeIds);
                    } else {
                        $sub->whereIn('brand_id', $officeIds);
                        $hasAny = true;
                    }
                }
            });

            // ✅ null country = multi-country viewer
            if (!empty($userCountryId)) {
                $baseQuery->where('country_id', $userCountryId);
            }

            // ✅ no mappings => no data
            if (empty($brandIds) && empty($officeIds)) {
                $baseQuery->whereRaw('1=0');
            }
        }

        // =========================
        // 1) MTD totals by country + currency
        // =========================
        $rows = (clone $baseQuery)
            ->selectRaw('country_id')
            ->selectRaw("COALESCE(currency, 'Unknown') as currency")
            ->selectRaw('COUNT(*) as incident_count')
            ->selectRaw('COALESCE(SUM(financial_loss), 0) as total_financial_loss')
            ->groupBy('country_id', 'currency')
            ->orderByDesc('total_financial_loss')
            ->get();

        $countryIds = $rows->pluck('country_id')->filter()->unique()->values()->all();

        $countryMap = [];
        if (!empty($countryIds)) {
            $countryMap = Country::whereIn('id', $countryIds)->pluck('name', 'id')->toArray();
        }

        $data = $rows->map(function ($row) use ($countryMap) {
            return [
                'country_id' => $row->country_id,
                'country' => $countryMap[$row->country_id] ?? 'Unknown',
                'currency' => $row->currency,
                'incident_count' => (int) $row->incident_count,
                'total_financial_loss' => round((float) $row->total_financial_loss, 2),
            ];
        })->values();

        // =========================
        // 2) Daily MTD totals by country + currency (for line graph)
        // =========================
        $dailyRows = (clone $baseQuery)
            ->selectRaw("DATE(incident_at) as day")
            ->selectRaw("country_id")
            ->selectRaw("COALESCE(currency, 'Unknown') as currency")
            ->selectRaw("COUNT(*) as incident_count")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as total_financial_loss")
            ->groupByRaw("DATE(incident_at), country_id, currency")
            ->orderBy("day")
            ->get();

        $dailyCountryIds = $dailyRows->pluck('country_id')->filter()->unique()->values()->all();

        $dailyCountryMap = [];
        if (!empty($dailyCountryIds)) {
            $dailyCountryMap = Country::whereIn('id', $dailyCountryIds)->pluck('name', 'id')->toArray();
        }

        $dailyByCountryCurrency = $dailyRows->map(function ($row) use ($dailyCountryMap) {
            return [
                'day' => $row->day,
                'country_id' => $row->country_id,
                'country' => $dailyCountryMap[$row->country_id] ?? 'Unknown',
                'currency' => $row->currency,
                'incident_count' => (int) $row->incident_count,
                'total_financial_loss' => round((float) $row->total_financial_loss, 2),
            ];
        })->values();

        return response()->json([
            'meta' => [
                'site_id' => $site->id,
                'timezone' => $tz,
                'is_super_admin' => $isSuperAdmin,
                'viewer_country_id' => $isSuperAdmin ? null : $userCountryId,
                'range' => [
                    'start' => $start->toDateTimeString(),
                    'end'   => $end->toDateTimeString(),
                ],
            ],
            'mtd_financial_loss_by_country_currency' => $data,
            'daily_by_country_currency' => $dailyByCountryCurrency,
        ], 200);
    }


    public function customIncidentReport(Request $request)
    {
        $site = app('site');
        $user = auth()->user();

        if (!$site || !$site->id) {
            return response()->json([
                'message' => 'Site context could not be resolved.',
            ], 500);
        }

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $siteId = $site->id;
        $tz = $request->input('tz', config('app.timezone', 'UTC'));

        $from = $request->input('from_date');
        $to = $request->input('to_date');

        $start = $from
            ? Carbon::parse($from, $tz)->startOfDay()
            : Carbon::now($tz)->subDays(30)->startOfDay();

        $end = $to
            ? Carbon::parse($to, $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();

        $dbStart = $start->copy()->timezone(config('app.timezone', 'UTC'));
        $dbEnd = $end->copy()->timezone(config('app.timezone', 'UTC'));

        $groupBy = $request->input('group_by', 'incident_type');
        $timeGrain = $request->input('time_grain', 'week');

        $allowedGroupBy = [
            'incident_type',
            'status',
            'severity',
            'country',
            'brand',
            'currency',
            'department',
        ];

        if (!in_array($groupBy, $allowedGroupBy, true)) {
            $groupBy = 'incident_type';
        }

        if (!in_array($timeGrain, ['day', 'week', 'month'], true)) {
            $timeGrain = 'week';
        }

        $asArray = function ($value) {
            if ($value === null || $value === '') {
                return [];
            }

            if (is_array($value)) {
                return array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
            }

            return [$value];
        };

        $countryIds = $asArray($request->input('country_ids'));
        $divisionIds = $asArray($request->input('division_ids'));
        $statuses = $asArray($request->input('statuses'));
        $severities = $asArray($request->input('severities'));
        $incidentTypes = $asArray($request->input('incident_types'));
        $currencies = $asArray($request->input('currencies'));

        $limit = (int) $request->input('limit', 100);
        $limit = max(10, min($limit, 500));

        $isSuperAdmin = (
            strtolower((string) ($user->role->name ?? '')) === 'super admin'
            || (bool) ($user->is_super_admin ?? false)
        );

        /*
         * IMPORTANT:
         * Both brand_id and office_id access mappings are matched against
         * incident_reports.brand_id because your incident table stores brands
         * and offices in that one shared column.
         */
        $mappedBrandIds = BrandOfficeManagement::where('user_id', $user->id)
            ->whereNotNull('brand_id')
            ->pluck('brand_id')
            ->unique()
            ->values()
            ->all();

        $mappedOfficeIds = BrandOfficeManagement::where('user_id', $user->id)
            ->whereNotNull('office_id')
            ->pluck('office_id')
            ->unique()
            ->values()
            ->all();

        $visibleDivisionIds = array_values(array_unique(array_merge(
            $mappedBrandIds,
            $mappedOfficeIds
        )));

        $userCountryId = optional($user->otherInfo)->country;

        $base = IncidentReport::query()
            ->where('site_id', $siteId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dbStart, $dbEnd]);

        if (!$isSuperAdmin) {
            if (empty($visibleDivisionIds)) {
                $base->whereRaw('1 = 0');
            } else {
                $base->whereIn('brand_id', $visibleDivisionIds);
            }

            if (!empty($userCountryId)) {
                $base->where('country_id', $userCountryId);
            }
        }

        if (!empty($countryIds)) {
            $base->whereIn('country_id', $countryIds);
        }

        if (!empty($divisionIds)) {
            // Brand and office IDs both compare against incident_reports.brand_id.
            $base->whereIn('brand_id', $divisionIds);
        }

        if (!empty($statuses)) {
            $base->whereIn('status', $statuses);
        }

        if (!empty($severities)) {
            $base->whereIn('severity', $severities);
        }

        if (!empty($incidentTypes)) {
            $base->whereIn('incident_type', $incidentTypes);
        }

        if (!empty($currencies)) {
            $base->whereIn('currency', $currencies);
        }

        $closedStatuses = ['closed', 'resolved'];
        $openStatuses = ['draft', 'submitted', 'under_review', 'investigating', 'rejected'];

        $countryMap = Country::query()
            ->whereNull('deleted_at')
            ->pluck('name', 'id')
            ->toArray();

        $brandMap = Brand::query()
            ->whereNull('deleted_at')
            ->pluck('name', 'id')
            ->toArray();

        $officeMap = Office::query()
            ->whereNull('deleted_at')
            ->pluck('name', 'id')
            ->toArray();

        $departmentMap = Depertment::query()
            ->whereNull('deleted_at')
            ->pluck('name', 'id')
            ->toArray();

        $divisionLabel = function ($id) use ($brandMap, $officeMap) {
            if (!$id) {
                return 'Unknown';
            }

            if (isset($brandMap[$id])) {
                return $brandMap[$id];
            }

            if (isset($officeMap[$id])) {
                return $officeMap[$id];
            }

            return 'Unknown';
        };

        $totalIncidents = (clone $base)->count();

        $openIncidents = (clone $base)
            ->whereIn('status', $openStatuses)
            ->count();

        $closedIncidents = (clone $base)
            ->whereIn('status', $closedStatuses)
            ->count();

        $criticalIncidents = (clone $base)
            ->where('severity', 'critical')
            ->count();

        $lossStillHappening = (clone $base)
            ->where('loss_still_happening', 1)
            ->count();

        $policeReported = (clone $base)
            ->where('police_reported', 1)
            ->count();

        $financial = (clone $base)
            ->selectRaw("COALESCE(currency, 'Unknown') as currency")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as financial_loss")
            ->selectRaw("COALESCE(SUM(amount_recovered), 0) as amount_recovered")
            ->selectRaw("COALESCE(SUM(amount_unrecovered), 0) as amount_unrecovered")
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'financial_loss' => round((float) $row->financial_loss, 2),
                'amount_recovered' => round((float) $row->amount_recovered, 2),
                'amount_unrecovered' => round((float) $row->amount_unrecovered, 2),
            ])
            ->values();

        $dateExpression = match ($timeGrain) {
            'day' => "DATE(created_at)",
            'month' => "DATE_FORMAT(created_at, '%Y-%m-01')",
            default => "DATE_FORMAT(DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(DATE(created_at)) DAY), '%Y-%m-%d')",
        };

        $trend = (clone $base)
            ->selectRaw("$dateExpression as period")
            ->selectRaw("COUNT(*) as incidents")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as financial_loss")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'label' => $this->formatIncidentTrendLabel($timeGrain, $row->period),
                'incidents' => (int) $row->incidents,
                'financial_loss' => round((float) $row->financial_loss, 2),
            ])
            ->values();

        /*
         * Country + currency trend.
         * This prevents mixing different currencies into one misleading loss graph.
         */
        $countryCurrencyTrend = (clone $base)
            ->selectRaw("$dateExpression as period")
            ->selectRaw("country_id")
            ->selectRaw("COALESCE(currency, 'Unknown') as currency")
            ->selectRaw("COUNT(*) as incidents")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as financial_loss")
            ->groupBy('period', 'country_id', 'currency')
            ->orderBy('period')
            ->get()
            ->map(function ($row) use ($timeGrain, $countryMap) {
                $country = $countryMap[$row->country_id] ?? 'Unknown';

                return [
                    'period' => (string) $row->period,
                    'label' => $this->formatIncidentTrendLabel($timeGrain, $row->period),
                    'country_id' => $row->country_id,
                    'country' => $country,
                    'currency' => $row->currency,
                    'series' => $country . ' - ' . $row->currency,
                    'incidents' => (int) $row->incidents,
                    'financial_loss' => round((float) $row->financial_loss, 2),
                ];
            })
            ->values();

        $groupColumn = match ($groupBy) {
            'status' => 'status',
            'severity' => 'severity',
            'country' => 'country_id',
            'brand' => 'brand_id',
            'currency' => 'currency',
            'department' => 'department_id',
            default => 'incident_type',
        };

        $grouped = (clone $base)
            ->selectRaw("COALESCE($groupColumn, 'Unknown') as group_key")
            ->selectRaw("COUNT(*) as incidents")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as financial_loss")
            ->selectRaw("COALESCE(SUM(amount_recovered), 0) as amount_recovered")
            ->selectRaw("COALESCE(SUM(amount_unrecovered), 0) as amount_unrecovered")
            ->groupBy('group_key')
            ->orderByDesc('incidents')
            ->get()
            ->map(function ($row) use ($groupBy, $countryMap, $divisionLabel, $departmentMap, $totalIncidents) {
                $key = $row->group_key;

                $label = match ($groupBy) {
                    'country' => $countryMap[$key] ?? 'Unknown',
                    'brand' => $divisionLabel($key),
                    'department' => $departmentMap[$key] ?? 'Unknown',
                    default => $key ?: 'Unknown',
                };

                $count = (int) $row->incidents;

                return [
                    'key' => $key,
                    'label' => $label,
                    'incidents' => $count,
                    'share_percent' => $totalIncidents > 0
                        ? round(($count / $totalIncidents) * 100, 2)
                        : 0,
                    'financial_loss' => round((float) $row->financial_loss, 2),
                    'amount_recovered' => round((float) $row->amount_recovered, 2),
                    'amount_unrecovered' => round((float) $row->amount_unrecovered, 2),
                ];
            })
            ->values();

        $statusSummary = (clone $base)
            ->selectRaw("COALESCE(status, 'Unknown') as status")
            ->selectRaw("COUNT(*) as incidents")
            ->groupBy('status')
            ->orderByDesc('incidents')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'incidents' => (int) $row->incidents,
            ])
            ->values();

        $severitySummary = (clone $base)
            ->selectRaw("COALESCE(severity, 'Unknown') as severity")
            ->selectRaw("COUNT(*) as incidents")
            ->groupBy('severity')
            ->orderByDesc('incidents')
            ->get()
            ->map(fn ($row) => [
                'severity' => $row->severity,
                'incidents' => (int) $row->incidents,
            ])
            ->values();

        $countryDivision = (clone $base)
            ->selectRaw("country_id")
            ->selectRaw("brand_id")
            ->selectRaw("COUNT(*) as incidents")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as financial_loss")
            ->groupBy('country_id', 'brand_id')
            ->orderByDesc('incidents')
            ->get()
            ->map(function ($row) use ($countryMap, $divisionLabel) {
                return [
                    'country_id' => $row->country_id,
                    'country' => $countryMap[$row->country_id] ?? 'Unknown',
                    'division_id' => $row->brand_id,
                    'division' => $divisionLabel($row->brand_id),
                    'incidents' => (int) $row->incidents,
                    'financial_loss' => round((float) $row->financial_loss, 2),
                ];
            })
            ->values();

        /*
         * Incident type occurrence by country.
         * This is a key report showing recurring incident types by country.
         */
        $incidentTypeCountry = (clone $base)
            ->selectRaw("country_id")
            ->selectRaw("COALESCE(incident_type, 'Unknown') as incident_type")
            ->selectRaw("COALESCE(currency, 'Unknown') as currency")
            ->selectRaw("COUNT(*) as incidents")
            ->selectRaw("COALESCE(SUM(financial_loss), 0) as financial_loss")
            ->groupBy('country_id', 'incident_type', 'currency')
            ->orderByDesc('incidents')
            ->get()
            ->map(function ($row) use ($countryMap) {
                return [
                    'country_id' => $row->country_id,
                    'country' => $countryMap[$row->country_id] ?? 'Unknown',
                    'incident_type' => $row->incident_type,
                    'currency' => $row->currency,
                    'incidents' => (int) $row->incidents,
                    'financial_loss' => round((float) $row->financial_loss, 2),
                ];
            })
            ->values();

        $visibleIncidentIds = (clone $base)
            ->pluck('id')
            ->values()
            ->all();

        $slaSummary = collect();

        if (!empty($visibleIncidentIds) && class_exists(IncidentSlaEvent::class)) {
            $slaSummary = IncidentSlaEvent::query()
                ->where('site_id', $siteId)
                ->whereNull('deleted_at')
                ->whereIn('incident_report_id', $visibleIncidentIds)
                ->selectRaw("COALESCE(event_type, 'Unknown') as event_type")
                ->selectRaw("COUNT(*) as total")
                ->selectRaw("AVG(minutes_value) as avg_minutes")
                ->selectRaw("MAX(minutes_value) as max_minutes")
                ->groupBy('event_type')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'event_type' => $row->event_type,
                    'total' => (int) $row->total,
                    'avg_minutes' => round((float) $row->avg_minutes, 2),
                    'max_minutes' => (int) $row->max_minutes,
                ])
                ->values();
        }

        $records = (clone $base)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'incident_number',
                'department_id',
                'division',
                'location',
                'incident_at',
                'reported_by',
                'accused',
                'incident_type',
                'other_incident_type',
                'incident_summary',
                'root_cause',
                'impact',
                'immediate_action',
                'corrective_action',
                'preventive_action',
                'loss_still_happening',
                'financial_loss',
                'amount_recovered',
                'amount_unrecovered',
                'currency',
                'police_required',
                'police_reported',
                'police_station',
                'police_case_number',
                'police_action_plan',
                'status',
                'severity',
                'respondent',
                'management_comment',
                'brand_id',
                'country_id',
                'how_incident_picked',
                'date_insurance_claim_submitted',
                'claim_number',
                'submitted_by',
                'created_at',
                'updated_at',
            ])
            ->map(function ($incident) use ($tz, $countryMap, $divisionLabel, $departmentMap) {
                return [
                    'id' => $incident->id,
                    'incident_number' => $incident->incident_number,
                    'department_id' => $incident->department_id,
                    'department' => $departmentMap[$incident->department_id] ?? null,
                    'division' => $incident->division,
                    'division_lookup' => $divisionLabel($incident->brand_id),
                    'location' => $incident->location,
                    'country_id' => $incident->country_id,
                    'country' => $countryMap[$incident->country_id] ?? 'Unknown',
                    'incident_at' => $incident->incident_at
                        ? Carbon::parse($incident->incident_at)->timezone($tz)->toDateTimeString()
                        : null,
                    'reported_by' => $incident->reported_by,
                    'accused' => $incident->accused,
                    'incident_type' => $incident->incident_type,
                    'other_incident_type' => $incident->other_incident_type,
                    'incident_summary' => $incident->incident_summary,
                    'root_cause' => $incident->root_cause,
                    'impact' => $incident->impact,
                    'immediate_action' => $incident->immediate_action,
                    'corrective_action' => $incident->corrective_action,
                    'preventive_action' => $incident->preventive_action,
                    'loss_still_happening' => (bool) $incident->loss_still_happening,
                    'financial_loss' => $incident->financial_loss === null ? null : round((float) $incident->financial_loss, 2),
                    'amount_recovered' => $incident->amount_recovered === null ? null : round((float) $incident->amount_recovered, 2),
                    'amount_unrecovered' => $incident->amount_unrecovered === null ? null : round((float) $incident->amount_unrecovered, 2),
                    'currency' => $incident->currency,
                    'police_required' => (bool) $incident->police_required,
                    'police_reported' => (bool) $incident->police_reported,
                    'police_station' => $incident->police_station,
                    'police_case_number' => $incident->police_case_number,
                    'police_action_plan' => $incident->police_action_plan,
                    'status' => $incident->status,
                    'severity' => $incident->severity,
                    'respondent' => $incident->respondent,
                    'management_comment' => $incident->management_comment,
                    'how_incident_picked' => $incident->how_incident_picked,
                    'date_insurance_claim_submitted' => $incident->date_insurance_claim_submitted,
                    'claim_number' => $incident->claim_number,
                    'submitted_by' => $incident->submitted_by,
                    'created_at' => $incident->created_at
                        ? Carbon::parse($incident->created_at)->timezone($tz)->toDateTimeString()
                        : null,
                    'updated_at' => $incident->updated_at
                        ? Carbon::parse($incident->updated_at)->timezone($tz)->toDateTimeString()
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Incident report generated successfully.',
            'meta' => [
                'site_id' => $siteId,
                'timezone' => $tz,
                'range' => [
                    'start' => $start->toDateTimeString(),
                    'end' => $end->toDateTimeString(),
                ],
                'is_super_admin' => $isSuperAdmin,
                'viewer_country_id' => $isSuperAdmin ? null : $userCountryId,
                'visible_division_ids' => $isSuperAdmin ? [] : $visibleDivisionIds,
                'office_ids_are_matched_against_incident_brand_id' => true,
                'filters' => [
                    'from_date' => $start->toDateString(),
                    'to_date' => $end->toDateString(),
                    'group_by' => $groupBy,
                    'time_grain' => $timeGrain,
                    'country_ids' => $countryIds,
                    'division_ids' => $divisionIds,
                    'statuses' => $statuses,
                    'severities' => $severities,
                    'incident_types' => $incidentTypes,
                    'currencies' => $currencies,
                    'limit' => $limit,
                ],
            ],
            'kpis' => [
                'total_incidents' => $totalIncidents,
                'open_incidents' => $openIncidents,
                'closed_incidents' => $closedIncidents,
                'critical_incidents' => $criticalIncidents,
                'loss_still_happening' => $lossStillHappening,
                'police_reported' => $policeReported,
            ],
            'financial' => $financial,
            'trend' => $trend,
            'country_currency_trend' => $countryCurrencyTrend,
            'grouped' => $grouped,
            'status_summary' => $statusSummary,
            'severity_summary' => $severitySummary,
            'country_division' => $countryDivision,
            'incident_type_country' => $incidentTypeCountry,
            'sla_summary' => $slaSummary,
            'records' => $records,
        ], 200);
    }

    private function formatIncidentTrendLabel(string $timeGrain, mixed $period): string
    {
        if (!$period) {
            return 'Unknown';
        }

        try {
            return match ($timeGrain) {
                'day' => Carbon::parse($period)->format('d M Y'),
                'month' => Carbon::parse($period)->format('M Y'),
                default => 'Week of ' . Carbon::parse($period)->format('d M Y'),
            };
        } catch (\Throwable $e) {
            return (string) $period;
        }
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
