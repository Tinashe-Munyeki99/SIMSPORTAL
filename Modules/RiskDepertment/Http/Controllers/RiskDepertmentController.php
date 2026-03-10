<?php

namespace Modules\RiskDepertment\Http\Controllers;

use App\Exports\IncidentReportsExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Authentication\Models\Brand;
use Modules\Authentication\Models\BrandOfficeManagement;
use Modules\Authentication\Models\Country;
use Modules\Authentication\Models\Office;
use Modules\Authentication\Models\SystemUser;
use Modules\RiskDepertment\Models\EmailsToEscalate;
use Modules\RiskDepertment\Models\IncidentAttachment;
use Modules\RiskDepertment\Models\IncidentNotificationReceipient;
use Modules\RiskDepertment\Models\IncidentNotificationRule;
use Modules\RiskDepertment\Models\IncidentReport;

use Modules\RiskDepertment\Models\IncidentSlaEvent;
use Modules\RiskDepertment\Models\IncidentType;
use Modules\RiskDepertment\Models\ReportStatusNotes;
use Modules\Site\Models\GeneralCounter;
use function App\Helpers\hasPermission;



class RiskDepertmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $site = app('site');
        $user = auth()->user();

        // permission flags (kept from your original)
        $canListAll = (hasPermission($user->role_id, 'list_incident', $site->id) === true);

        $isSuperAdmin = (
            strtolower((string)($user->role->name ?? '')) === 'super admin'
            || (bool)($user->is_super_admin ?? false)
        );

        // ✅ validate request filters
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['draft','submitted','under_review','investigating','resolved','closed','rejected'])],
            'severity' => ['nullable', Rule::in(['low','medium','high','critical'])],
            'incident_type' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'q' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:200',

            'country_id' => 'nullable|uuid',
            'brand_id' => 'nullable|uuid',
            'reported_by_user_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        // support both from/to and from_date/to_date
        $fromInput = $request->input('from', $request->input('from_date'));
        $toInput   = $request->input('to',   $request->input('to_date'));

        $query = IncidentReport::query()
            ->where('site_id', $site->id);

        /**
         * ✅ Visibility rules
         * - Super Admin: all incidents in site
         * - Non-super admin: only incidents for their brand_ids OR office_ids (based on BrandOfficeManagement)
         *   NOTE: your original code had a bug: officeIds were used against brand_id.
         *   This implementation assumes your IncidentReport has BOTH columns: brand_id and office_id.
         *   If IncidentReport does NOT have office_id, see NOTE below.
         */
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

        if (!$isSuperAdmin) {
            $query->where(function ($sub) use ($brandIds, $officeIds) {
                $hasAny = false;

                if (!empty($brandIds)) {
                    $sub->whereIn('brand_id', $brandIds);
                    $hasAny = true;
                }

                // ✅ correct: office visibility should match office_id column (if it exists)
                if (!empty($officeIds)) {
                    // if brand filter already applied, use OR; otherwise just whereIn
                    if ($hasAny) {
                        $sub->orWhereIn('brand_id', $officeIds);
                    } else {
                        $sub->whereIn('brand_id', $officeIds);
                        $hasAny = true;
                    }
                }

                // if no mappings, this closure remains empty; handled below
            });

            // ✅ If user has no mappings, return none
            if (empty($brandIds) && empty($officeIds)) {
                $query->whereRaw('1=0');
            }
        }

        // ---------------- Filters ----------------
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('incident_type')) $query->where('incident_type', $request->incident_type);

        // date filters (simple)
        if ($request->filled('from')) $query->whereDate('incident_at', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('incident_at', '<=', $request->to);

        if ($request->filled('q')) {
            $q = $request->q;

            $query->where(function ($sub) use ($q) {
                $sub->where('incident_number', 'like', "%{$q}%")   // ✅ ADD THIS
                ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('division', 'like', "%{$q}%")
                    ->orWhere('reported_by', 'like', "%{$q}%")
                    ->orWhere('accused', 'like', "%{$q}%")
                    ->orWhere('incident_summary', 'like', "%{$q}%")
                    ->orWhere('root_cause', 'like', "%{$q}%");
            });
        }


        // date filters (range)
        if ($fromInput && $toInput) {
            $from = Carbon::parse($fromInput)->startOfDay();
            $to   = Carbon::parse($toInput)->endOfDay();
            $query->whereBetween('incident_at', [$from, $to]);
        } elseif ($fromInput) {
            $query->where('incident_at', '>=', Carbon::parse($fromInput)->startOfDay());
        } elseif ($toInput) {
            $query->where('incident_at', '<=', Carbon::parse($toInput)->endOfDay());
        }

        if ($request->filled('country_id')) $query->where('country_id', $request->country_id);

        // brand filter narrows results only
        if ($request->filled('brand_id')) $query->where('brand_id', $request->brand_id);

        // reported_by is varchar, so filter by created_by
        if ($request->filled('reported_by_user_id')) {
            $query->where('created_by', $request->reported_by_user_id);
        }

        $perPage = (int) $request->input('per_page', 15);

        // ---------------- Fetch data ----------------
        $data = $query->with(['attachments','brand','reportedBy','country','reportReviews'])
            ->latest('created_at')
            ->paginate($perPage);

        // ---------------- SLA LOGIC ----------------
        // Load all incident type presets in one query (no N+1)
        $incidentTypeNames = collect($data->items())
            ->pluck('incident_type')
            ->filter()
            ->unique()
            ->values();

        $typePresets = IncidentType::query()

            ->whereIn('name', $incidentTypeNames)
            ->get()
            ->keyBy('name');

        $now = Carbon::now();

        // Closed statuses
        $closedStatuses = ['resolved','closed','rejected'];

        $enriched = collect($data->items())->map(function ($incident) use ($typePresets, $now, $site, $closedStatuses) {
            $preset = $typePresets->get($incident->incident_type);

            $expectedResponse = (int) ($preset->expected_response_minutes ?? 0);
            $expectedClose    = (int) ($preset->expected_close_minutes ?? 0);

            $incidentAt = $incident->incident_at ? Carbon::parse($incident->incident_at) : null;
            $createdAt  = $incident->created_at  ? Carbon::parse($incident->created_at)  : null;

            // -------- Response SLA (late logging) --------
            $responseDelayMinutes = null;
            $lateLogged = false;
            $lateByMinutes = null;

            if ($incidentAt && $createdAt) {
                // created_at should be after incident_at; if not, clamp to 0
                $diff = $incidentAt->diffInMinutes($createdAt, false); // negative if created before incidentAt
                $responseDelayMinutes = max(0, $diff < 0 ? 0 : $diff);

                if ($expectedResponse > 0 && $responseDelayMinutes > $expectedResponse) {
                    $lateLogged = true;
                    $lateByMinutes = $responseDelayMinutes - $expectedResponse;

                    // ✅ Create a child event (idempotent)
                    IncidentSlaEvent::firstOrCreate(
                        [
                            'site_id' => $site->id,
                            'incident_report_id' => $incident->id,
                            'event_type' => 'late_log',
                        ],
                        [
                            'minutes_value' => $lateByMinutes,
                            'meta' => json_encode([
                                'expected_response_minutes' => $expectedResponse,
                                'response_delay_minutes' => $responseDelayMinutes,
                                'incident_at' => (string) $incidentAt,
                                'created_at' => (string) $createdAt,
                            ]),
                        ]
                    );
                }
            }

            // -------- Close SLA (days left / overdue) --------
            $isClosed = in_array((string)$incident->status, $closedStatuses, true);

            $minutesSinceCreated = null;
            $closeRemainingMinutes = null;
            $daysLeftToClose = null;
            $daysOverdue = null;

            if ($createdAt) {
                $minutesSinceCreated = $createdAt->diffInMinutes($now);

                if (!$isClosed && $expectedClose > 0) {
                    $closeRemainingMinutes = $expectedClose - $minutesSinceCreated;

                    if ($closeRemainingMinutes >= 0) {
                        $daysLeftToClose = (int) ceil($closeRemainingMinutes / 1440);
                        $daysOverdue = 0;
                    } else {
                        $daysLeftToClose = 0;
                        $daysOverdue = (int) ceil(abs($closeRemainingMinutes) / 1440);

                        // ✅ Create overdue child event (idempotent)
                        IncidentSlaEvent::firstOrCreate(
                            [
                                'site_id' => $site->id,
                                'incident_report_id' => $incident->id,
                                'event_type' => 'close_overdue',
                            ],
                            [
                                'minutes_value' => abs($closeRemainingMinutes),
                                'meta' => json_encode([
                                    'expected_close_minutes' => $expectedClose,
                                    'minutes_since_created' => $minutesSinceCreated,
                                    'created_at' => (string) $createdAt,
                                    'now' => (string) $now,
                                ]),
                            ]
                        );
                    }
                }
            }

            // Attach computed SLA info to JSON output
            $incident->sla = [
                'preset' => [
                    'expected_response_minutes' => $expectedResponse,
                    'expected_close_minutes' => $expectedClose,
                ],
                'response' => [
                    'response_delay_minutes' => $responseDelayMinutes,
                    'late_logged' => $lateLogged,
                    'late_by_minutes' => $lateByMinutes,
                ],
                'close' => [
                    'is_closed' => $isClosed,
                    'minutes_since_created' => $minutesSinceCreated,
                    'close_remaining_minutes' => $closeRemainingMinutes,
                    'days_left_to_close' => $daysLeftToClose,
                    'days_overdue' => $daysOverdue,
                ],
            ];

            return $incident;
        });

        // keep paginator meta; replace items
        $data->setCollection($enriched);

        return response()->json($data, 200);
    }


    private function incidentNumber(string $country_id): string
    {
        $country = Country::findOrFail($country_id);

        // ✅ prefix: first 3 letters of country name (or use iso3 if you have it)
        $prefix = strtoupper(substr(preg_replace('/\s+/', '', $country->name), 0, 3));

        // ✅ Atomic increment using DB transaction + lock
        $nextValue = DB::transaction(function () use ($prefix) {
            // lock row so no two requests get same number
            $counter = GeneralCounter::where('name', $prefix)->lockForUpdate()->first();

            if (!$counter) {
                $counter = new GeneralCounter();
                $counter->id = (string) Str::uuid();
                $counter->name = $prefix;
                $counter->counter_value = 0;
            }

            $counter->counter_value = (int) $counter->counter_value + 1;
            $counter->save();

            return (int) $counter->counter_value;
        });

        // ✅ format: ZIM0001 (pad to 4 digits)
        return $prefix . str_pad((string) $nextValue, 4, '0', STR_PAD_LEFT);
    }





    /**
     * POST /incident-reports
     */
    public function store(Request $request)
    {
        $site = app('site');
        $userId = optional($request->user())->id;

        $country_id = optional($request->user())->otherInfo->country;

        $incidentNumber = $this->incidentNumber($country_id);

        $validator = Validator::make($request->all(), [
            'division' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'incident_at' => 'required|date',

            'incident_number' => 'nullable|string|max:100',

            'reported_by' => 'nullable|string|max:255',
            'accused' => 'nullable|string|max:255',

            'incident_type' => 'required|string|max:255',
            'other_incident_type' => 'nullable|string|max:255',

            'incident_summary' => 'nullable|string|max:255',
            'root_cause' => 'nullable|string',
            'impact' => 'nullable|string',

            'immediate_action' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'preventive_action' => 'nullable|string',

            'loss_still_happening' => 'nullable|boolean',

            'financial_loss' => 'nullable|numeric|min:0',
            'amount_recovered' => 'nullable|numeric|min:0',
            'amount_unrecovered' => 'nullable|numeric|min:0',

            'currency' => 'nullable|string|max:10',

            'police_required' => 'nullable|boolean',
            'police_reported' => 'nullable|boolean',
            'police_station' => 'nullable|string|max:255',
            'police_case_number' => 'nullable|string|max:255',
            'police_action_plan' => 'nullable|string',

            'how_incident_picked' => 'nullable|string',
            'date_insurance_claim_submitted' => 'nullable|date',
            'claim_number' => 'nullable|string|max:100',
            'submitted_by' => 'nullable|string|max:100',

            'status' => ['nullable', Rule::in(['draft','submitted','under_review','investigating','resolved','closed','rejected'])],
            'severity' => ['nullable', Rule::in(['low','medium','high','critical'])],

            'management_comment' => 'nullable|string',

            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,csv',
        ])->after(function ($validator) use ($request) {
            if (strtolower(trim((string)$request->incident_type)) === 'other') {
                if (!trim((string)$request->other_incident_type)) {
                    $validator->errors()->add('other_incident_type', 'Other incident type is required.');
                }
            }

            $financial = $request->financial_loss !== null ? (float)$request->financial_loss : null;
            $recovered = $request->amount_recovered !== null ? (float)$request->amount_recovered : null;
            $unrec     = $request->amount_unrecovered !== null ? (float)$request->amount_unrecovered : null;

            if ($financial !== null) {
                $sum = (float)($recovered ?? 0) + (float)($unrec ?? 0);
                if ($sum > $financial) {
                    $validator->errors()->add('amount_recovered', 'Recovered + Unrecovered cannot exceed Financial Loss.');
                    $validator->errors()->add('amount_unrecovered', 'Recovered + Unrecovered cannot exceed Financial Loss.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        // ✅ Resolve "division" to a brand_id (brand first, then office)
        $divisionInput = trim((string)($payload['division'] ?? ''));

        $resolvedBrandId = null;

        if ($divisionInput !== '') {
            // 1) try brands (match by id OR name OR brand_id code)
            $resolvedBrandId = Brand::query()
                ->where('id', $divisionInput)
                ->orWhere('name', $divisionInput)
                ->value('id');

            // 2) if not found, try offices (match by id OR name)
            if (!$resolvedBrandId) {
                $resolvedBrandId = Office::query()
                    ->where('id', $divisionInput)
                    ->orWhere('name', $divisionInput)
                    ->value('id');
            }

            // 3) still not found -> throw validation error
            if (!$resolvedBrandId) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Validation error',
                    'data' => [
                        'division' => ['Division not found in brands or offices.'],
                    ],
                ], 422);
            }
        }

        try {
            $incident = DB::transaction(function () use ($request, $payload, $site, $userId, $country_id, $incidentNumber, $resolvedBrandId) {

                $payload['incident_number'] = $incidentNumber;
                $payload['site_id']    = $site->id;
                $payload['country_id'] = $country_id;

                // ✅ save resolved id into brand_id
                $payload['brand_id']   = $resolvedBrandId;

                $payload['created_by'] = $userId;
                $payload['updated_by'] = $userId;
                $payload['reported_by'] = $userId;

                // ✅ when submitted -> under_review
                $incomingStatus = $payload['status'] ?? null;
                $payload['status'] = (!$incomingStatus || $incomingStatus === 'submitted')
                    ? 'under_review'
                    : $incomingStatus;

                $payload['severity'] = $payload['severity'] ?? 'low';
                $payload['currency'] = $payload['currency'] ?? 'USD';

                $payload['loss_still_happening'] = (bool)($payload['loss_still_happening'] ?? false);
                $payload['police_required']      = (bool)($payload['police_required'] ?? false);
                $payload['police_reported']      = (bool)($payload['police_reported'] ?? false);

                if (strtolower(trim((string)$payload['incident_type'])) === 'other') {
                    $payload['other_incident_type'] = trim((string)($payload['other_incident_type'] ?? ''));
                } else {
                    $payload['other_incident_type'] = null;
                }

                /** @var IncidentReport $incident */
                $incident = IncidentReport::create($payload);

                // attachments
                if ($request->hasFile('attachments')) {
                    $basePath = base_path('assets/incident_reports/' . $incident->id);
                    if (!file_exists($basePath)) mkdir($basePath, 0755, true);

                    foreach ($request->file('attachments') as $file) {
                        if (!$file || !$file->isValid()) continue;

                        $originalName = $file->getClientOriginalName();
                        $safeName = time() . '_' . Str::random(6) . '_' . $originalName;

                        $file->move($basePath, $safeName);

                        IncidentAttachment::create([
                            'id' => (string) Str::uuid(),
                            'incident_report_id' => $incident->id,
                            'file_name' => $originalName,
                            'file_path' => 'assets/incident_reports/' . $incident->id . '/' . $safeName,
                            'mime_type' => $file->getClientMimeType(),
                            'uploaded_by' => $userId,
                        ]);
                    }
                }

                return $incident;
            });

            try {
                $this->notifyMe(
                    $incident->incident_number,
                    optional($request->user())->email ?? '',
                    (string) $country_id,
                    (string) ($payload['division'] ?? ''),
                    (string) ($payload['location'] ?? '')
                );
            } catch (\Throwable $e) {
                // don’t break incident creation if email fails
                Log::error("Incident notify failed: ".$e->getMessage(), [
                    'incident_number' => $incident->incident_number ?? null,
                    'sender' => optional($request->user())->email ?? null
                ]);
            }

            try {
                $currency = (string) ($incident->currency ?? ($payload['currency'] ?? 'USD'));
                $amount   = (float)  ($incident->financial_loss ?? ($payload['financial_loss'] ?? 0));

                // only escalate if there is a positive loss
                if ($amount > 0) {
                    $ruleResult = $this->checkEmailNotificationRule($currency, $amount);

                    if ($ruleResult && !empty($ruleResult['emails'])) {

                        // TO recipients from the rule
                        $toRecipients = $this->normalizeEmails($ruleResult['emails']);

                        // OPTIONAL: add reporter in CC or add extra CC logic
                        $ccRecipients = [];

                        // If you want to copy reporter automatically:
                        $reporterEmail = optional($request->user())->email;
                        if ($reporterEmail) {
                            $ccRecipients = $this->normalizeEmails([$reporterEmail]);
                            $ccRecipients = array_values(array_diff($ccRecipients, $toRecipients));
                        }

                        // ✅ Send the escalation email (PDF attached)
                        $this->sendIncidentEscalationEmail(
                            incidentId: (string)$incident->id,
                            toRecipients: $toRecipients,
                            ccRecipients: $ccRecipients,
                            subjectPrefix: 'Auto Escalation'
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Auto escalation failed: ".$e->getMessage(), [
                    'incident_id' => $incident->id ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Incident report created',
                'data' => $incident->load('attachments'),
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Something went wrong while creating the incident report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeEmails($arr): array
    {
        $arr = is_array($arr) ? $arr : [];
        $arr = array_map(fn ($e) => strtolower(trim((string) $e)), $arr);
        $arr = array_filter($arr, fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL));
        return array_values(array_unique($arr));
    }


    private function notifyMe(
        string $incidentNumber,
        string $senderEmail,
        string $country,
        string $division,
        string $location
    ): void {
        $site = app('site');

        if (!$senderEmail) {
            throw new \Exception("Sender email missing (logged-in user has no email).");
        }

        // Get incident details
        $incident = IncidentReport::query()
            ->with(['reportedBy']) // optional if relationship exists
            ->where('incident_number', $incidentNumber)
            ->first();

        if (!$incident) {
            throw new \Exception("Incident not found for incident_number: {$incidentNumber}");
        }

        $incidentDate = $incident->incident_at
            ? \Carbon\Carbon::parse($incident->incident_at)->format('Y-m-d H:i:s')
            : 'N/A';

        $severity = $incident->severity ?? 'N/A';

        $reporterName =
            optional($incident->reportedBy)->full_name
            ?? $senderEmail;

        $summary = $incident->incident_summary ?? 'N/A';

        $country =Country::where("id",$country)->first();

        // Optional: Only Risk department users
        $candidates = SystemUser::query()
            ->with(['department'])
            ->whereNotNull('email')
            ->whereHas('department', function ($q) {
                $q->where('name', 'Risk');
            })
            ->get();

        // Filter by permission
        $recipients = $candidates->filter(function ($user) use ($site) {
            return hasPermission($user->role_id, 'notify_incident', $site->id) === true;
        });

        $to = $recipients
            ->pluck('email')
            ->filter()
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()
            ->values()
            ->toArray();

        // Remove sender if present
        $to = array_values(array_diff($to, [strtolower(trim($senderEmail))]));

        if (empty($to)) {
            Log::warning("No recipients found for incident notification", [
                'incident_number' => $incidentNumber,
                'site_id' => $site->id ?? null
            ]);
            return;
        }

        $subject = "Incident Reported – {$incidentNumber}";

        $body =
            "An incident has been reported.\n\n"
            . "Incident Number: {$incidentNumber}\n"
            . "Location: {$division} – {$location} - {$country->name}\n"
            . "Severity: {$severity}\n"
            . "Reported By: {$reporterName}\n"
            . "Date: {$incidentDate}\n\n"
            . "Summary:\n"
            . "{$summary}\n\n"
            . "Please review the incident in the system.";

        $ok = $this->sendGraphEmailWithAttachmentDomainAware(
            $senderEmail,
            $subject,
            $body,
            $to,
            [],
            '',
            '',
            'application/octet-stream'
        );

        if (!$ok) {
            throw new \Exception("Graph email sending returned false.");
        }

        Log::info("Incident notification sent", [
            'incident_number' => $incidentNumber,
            'from' => $senderEmail,
            'to_count' => count($to),
            'to' => $to,
        ]);
    }

    private function checkEmailNotificationRule(string $currency, float $amount)
    {
        $rule = IncidentNotificationRule::query()
            ->where('currency', $currency)
            ->where('is_active', 1)
            ->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $amount);
            })
            ->orderByRaw('max_amount IS NULL ASC') // prefer bounded rules first
            ->orderBy('min_amount', 'desc')        // prefer the highest min that still matches
            ->first();

        if (!$rule) {
            return null;
        }

        // Get recipients for this rule
        $emails = IncidentNotificationReceipient::query()
            ->where('rule_id', $rule->id)
            ->pluck('email')
            ->toArray();

        return [
            'rule' => $rule,
            'emails' => $emails,
        ];
    }

    private function sendIncidentEscalationEmail(
        string $incidentId,
        array $toRecipients,
        array $ccRecipients = [],
        string $subjectPrefix = 'Incident Escalation'
    ): bool {
        $site = app('site');

        // ✅ normalize + ensure TO exists
        $toRecipients = $this->normalizeEmails($toRecipients);
        $ccRecipients = $this->normalizeEmails($ccRecipients);

        if (count($toRecipients) < 1) {
            return false;
        }

        // ✅ load incident (same as your escalateIncident)
        $incident = IncidentReport::with([
            'reportReviews' => function ($query) {
                $query->with(['user' => function ($q) {
                    $q->with(['otherInfo']);
                }]);
            },
            'attachments',
            'brand',
            'reportedBy' => function ($query) {
                $query->with(['otherInfo']);
            },
            'country',
        ])
            ->where('site_id', $site->id)
            ->where('id', $incidentId)
            ->firstOrFail();

        // ✅ Sender email (must exist)
        $fromUserEmail = trim((string) (auth()->user()?->email ?? ''));
        if ($fromUserEmail === '') {
            // fallback: if store() is called by API without auth context
            // you can fallback to a system mailbox if you have one
            throw new \RuntimeException('Logged-in user email not found (cannot send).');
        }

        // ✅ Remove overlap
        $ccRecipients = array_values(array_diff($ccRecipients, $toRecipients));

        // ✅ Build PDF HTML from blade
        $html = view('riskdepertment::incident_pdf', [
            'incident'    => $incident,
            'generatedAt' => now(),
            'site'        => $site,
        ])->render();

        // ✅ Generate PDF
        $pdfBytes = $this->renderPdfFromHtml($html, 'A4', 'portrait');

        $incidentNo = (string) ($incident->incident_number ?? $incident->id);
        $attachmentName = "Incident_{$incidentNo}_" . now()->format('Ymd_His') . ".pdf";

        // ✅ Subject/body
        $subject = "{$subjectPrefix} ({$incidentNo})";
        $body =
            "Hi,\n\n" .
            "Incident {$incidentNo} has been escalated automatically based on financial loss thresholds.\n" .
            "Please find the incident report attached.\n\n" .
            "Regards,\n" .
            (auth()->user()?->full_name ?? 'Simbisa Brands');

        // ✅ Send via Graph
        return $this->sendGraphEmailWithAttachmentDomainAware(
            fromUserEmail: $fromUserEmail,
            subject: $subject,
            body: $body,
            toRecipients: $toRecipients,
            ccRecipients: $ccRecipients,
            attachmentName: $attachmentName,
            attachmentBytes: $pdfBytes,
            attachmentMime: 'application/pdf'
        );
    }


    /**
     * GET /incident-reports/{id}
     */
    public function show(Request $request, string $id)
    {
        $site = app('site');

        $incident = IncidentReport::with(['reportReviews'=>function ($query) {
            $query->with(["user"=>function($query){
                $query->with(["otherInfo"]);
            }]);

        },'attachments','brand','reportedBy'=>function ($query) {
            $query->with(["otherInfo"]);

        },'country'])
            ->where('site_id', $site->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['data' => $incident], 200);
    }

    /**
     * PATCH /incident-reports/{id}
     */
    public function update(Request $request, string $id)
    {
        $site = app('site');
        $userId = optional($request->user())->id;

        $validator = Validator::make($request->all(), [
            'division' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'incident_at' => 'nullable|date',

            'reported_by' => 'nullable|string|max:255',
            'accused' => 'nullable|string|max:255',

            'incident_type' => 'nullable|string|max:100',
            'incident_summary' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'impact' => 'nullable|string',

            'immediate_action' => 'nullable|string',
            'corrective_action' => 'nullable|string',
            'preventive_action' => 'nullable|string',

            'loss_still_happening' => 'nullable|boolean',
            'financial_loss' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',

            'police_required' => 'nullable|boolean',
            'police_reported' => 'nullable|boolean',
            'police_station' => 'nullable|string|max:255',
            'police_case_number' => 'nullable|string|max:255',
            'police_action_plan' => 'nullable|string',

            'status' => ['nullable', Rule::in(['draft','submitted','under_review','investigating','resolved','closed','rejected'])],
            'severity' => ['nullable', Rule::in(['low','medium','high','critical'])],

            'respondent' => 'nullable|string|max:255',
            'management_comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $incident = IncidentReport::where('site_id', $site->id)->where('id', $id)->firstOrFail();

        $payload = $validator->validated();
        $payload['updated_by'] = $userId;

        $incident->update($payload);

        return response()->json([
            'message' => 'Incident report updated',
            'data' => $incident->fresh()->load('attachments'),
        ], 200);
    }

    /**
     * DELETE /incident-reports/{id}
     */
    public function destroy(Request $request, string $id)
    {
        $site = app('site');

        $incident = IncidentReport::with('attachments')
            ->where('site_id', $site->id)
            ->where('id', $id)
            ->firstOrFail();

        // delete files too (optional)
        foreach ($incident->attachments as $att) {
            Storage::disk('public')->delete($att->file_path);
            $att->delete();
        }

        $incident->delete();

        return response()->json(['message' => 'Incident report deleted'], 200);
    }

    /**
     * POST /incident-reports/{id}/attachments
     * multipart/form-data: file
     */
    public function uploadAttachment(Request $request, string $id)
    {
        $site = app('site');
        $userId = optional($request->user())->id;

        $incident = IncidentReport::where('site_id', $site->id)->where('id', $id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store("incident_reports/{$incident->id}", 'public');

        $attachment = IncidentAttachment::create([
            'incident_report_id' => $incident->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        return response()->json([
            'message' => 'Attachment uploaded',
            'data' => $attachment,
        ], 201);
    }

    /**
     * DELETE /incident-attachments/{attachmentId}
     */
    public function deleteAttachment(Request $request, string $attachmentId)
    {
        $site = app('site');

        $attachment = IncidentAttachment::where('id', $attachmentId)->firstOrFail();

        // ensure belongs to same site (via parent)
        IncidentReport::where('site_id', $site->id)
            ->where('id', $attachment->incident_report_id)
            ->firstOrFail();

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted'], 200);
    }




    public function updateReportStatus(Request $request, string $id, string $status)
    {
        $site = app('site');

        // Status -> permission map
        $statusPermissions = [
            'closed'         => 'close_report',
            'under_review'   => 'review_report',
            'investigating'  => 'investigating_issue',
            'resolved'       => 'resolve_issue',
            'rejected'       => 'reject_issue',
        ];

        $status = strtolower(trim((string) $status));

        // Validate status
        if (!array_key_exists($status, $statusPermissions)) {
            return response()->json(['message' => 'Invalid status'], 422);
        }

        // Permission check
        $permission = $statusPermissions[$status];
        $result = hasPermission(auth()->user()->role_id, $permission, $site->id);
        if ($result !== true) {
            return $result;
        }

        // Validation rules (conditional)
        $rules = [
            'user_id' => 'required|exists:system_users,id',
            'notes'   => 'nullable|string',
        ];

        // Notes required when rejected OR investigating (as requested)
        if (in_array($status, ['rejected', 'investigating'], true)) {
            $rules['notes'] = 'required|string|min:3';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        // Get report
        $report = IncidentReport::where('site_id', $site->id)
            ->where('id', $id)
            ->firstOrFail();

        /**
         * ✅ Enforce workflow:
         * submitted -> under_review -> investigating -> resolved -> closed
         * plus: can reject from submitted/under_review/investigating
         */
        $current = strtolower((string) $report->status);

        $allowedTransitions = [
            'submitted'      => ['under_review', 'rejected'],
            'under_review'   => ['investigating', 'rejected'],
            'investigating'  => ['resolved', 'rejected'],
            'resolved'       => ['closed'],
            'closed'         => [],
            'rejected'       => [],
        ];

        if (!isset($allowedTransitions[$current])) {
            return response()->json([
                'status'  => 422,
                'message' => "Invalid current status '{$report->status}'.",
            ], 422);
        }

        if (!in_array($status, $allowedTransitions[$current], true)) {
            return response()->json([
                'status'  => 422,
                'message' => "You cannot change status from '{$report->status}' to '{$status}'. You must investigate before resolving/closing.",
            ], 422);
        }

        // Apply status-specific updates
        switch ($status) {
            case 'under_review':
                $report->status = 'under_review';
                $report->reviewed_by = $request->user_id;
                $report->save();
                break;

            case 'investigating':
                $report->status = 'investigating';
                $report->investigated_by = $request->user_id; // if column exists
                $report->save();
                break;

            case 'resolved':
                $report->status = 'resolved';
                $report->resolved_by = $request->user_id; // if column exists
                $report->save();
                break;

            case 'closed':
                $report->status = 'closed';
                $report->closed_by = $request->user_id; // if column exists
                $report->save();
                break;

            case 'rejected':
                $report->status = 'rejected';
                $report->rejected_by = $request->user_id; // if column exists
                $report->save();
                break;
        }

        // Always log a status note for every status change
        $note = new ReportStatusNotes();
        $note->incident_report_id = $report->id;
        $note->user_id = $request->user_id;
        $note->status = $status;
        $note->notes = $request->notes; // required for rejected & investigating, optional otherwise
        $note->save();

        return response()->json([
            'status'  => 200,
            'message' => 'Report status updated successfully',
            'data'    => [
                'id'     => $report->id,
                'status' => $report->status,
            ],
        ], 200);
    }





    public function listIncidentTypes(Request $request)
    {
        $per_page = (int) ($request->get('per_page', 50));
        $q = trim((string) $request->get('q', ''));

        $query = IncidentType::query()->whereNull('deleted_at');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('victim_label', 'like', "%{$q}%");
            });
        }

        $data = $query->orderBy('name')->paginate($per_page);

        return response()->json([
            'incident_types' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    // ✅ GET ONE
    public function getIncidentType($id)
    {
        $type = IncidentType::where('id', $id)->whereNull('deleted_at')->first();

        if (!$type) {
            return response()->json([
                'message' => 'Incident type not found.',
            ], 404);
        }

        return response()->json([
            'incident_type' => $type,
        ]);
    }

    // ✅ CREATE
    public function createIncidentType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('incident_types', 'name')->where(function ($q) {
                    $q->whereNull('deleted_at');
                }),
            ],
            'expected_response_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'expected_close_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'victim_label' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => 422,
                "message" => "Validation error",
                "data" => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            $type = new IncidentType();
            $type->id = (string) Str::uuid();
            $type->name = $data['name'];
            $type->expected_response_minutes = $data['expected_response_minutes'] ?? null;
            $type->expected_close_minutes = $data['expected_close_minutes'] ?? null;
            $type->victim_label = $data['victim_label'] ?? null;
            $type->save();

            return response()->json([
                "message" => "Incident type created successfully.",
                "incident_type" => $type,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Something went wrong while creating incident type.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ UPDATE
    public function updateIncidentType(Request $request, $id)
    {
        $type = IncidentType::where('id', $id)->whereNull('deleted_at')->first();

        if (!$type) {
            return response()->json([
                'message' => 'Incident type not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('incident_types', 'name')
                    ->where(function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->ignore($id, 'id'),
            ],
            'expected_response_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'expected_close_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'victim_label' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => 422,
                "message" => "Validation error",
                "data" => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            $type->name = $data['name'];
            $type->expected_response_minutes = $data['expected_response_minutes'] ?? null;
            $type->expected_close_minutes = $data['expected_close_minutes'] ?? null;
            $type->victim_label = $data['victim_label'] ?? null;
            $type->save();

            return response()->json([
                "message" => "Incident type updated successfully.",
                "incident_type" => $type,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Something went wrong while updating incident type.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    // ✅ DELETE (soft delete)
    public function deleteIncidentType($id)
    {
        $type = IncidentType::where('id', $id)->whereNull('deleted_at')->first();

        if (!$type) {
            return response()->json([
                'message' => 'Incident type not found.',
            ], 404);
        }

        try {
            $type->delete();

            return response()->json([
                "message" => "Incident type deleted successfully.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Something went wrong while deleting incident type.",
                "error" => $e->getMessage(),
            ], 500);
        }
    }





    private function buildIncidentQuery(Request $request)
    {
        $site = app('site');
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['draft','submitted','under_review','investigating','resolved','closed','rejected'])],
            'severity' => ['nullable', Rule::in(['low','medium','high','critical'])],
            'incident_type' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'q' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:200',
            'country_id' => 'nullable|uuid',
            'brand_id' => 'nullable|uuid',
            'reported_by_user_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            abort(response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422));
        }

        $fromInput = $request->input('from', $request->input('from_date'));
        $toInput   = $request->input('to',   $request->input('to_date'));

        $query = IncidentReport::query()->where('site_id', $site->id);

        $isSuperAdmin = (
            strtolower((string)($user->role->name ?? '')) === 'super admin'
            || (bool)($user->is_super_admin ?? false)
        );

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

        if (!$isSuperAdmin) {
            $query->where(function ($sub) use ($brandIds, $officeIds) {
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

            if (empty($brandIds) && empty($officeIds)) {
                $query->whereRaw('1=0');
            }
        }

        // Filters
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('severity')) $query->where('severity', $request->severity);
        if ($request->filled('incident_type')) $query->where('incident_type', $request->incident_type);

        if ($request->filled('from')) $query->whereDate('incident_at', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('incident_at', '<=', $request->to);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('incident_number', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('division', 'like', "%{$q}%")
                    ->orWhere('reported_by', 'like', "%{$q}%")
                    ->orWhere('accused', 'like', "%{$q}%")
                    ->orWhere('incident_summary', 'like', "%{$q}%")
                    ->orWhere('root_cause', 'like', "%{$q}%");
            });
        }

        // Range dates (support from/to and from_date/to_date)
        if ($fromInput && $toInput) {
            $from = Carbon::parse($fromInput)->startOfDay();
            $to   = Carbon::parse($toInput)->endOfDay();
            $query->whereBetween('incident_at', [$from, $to]);
        } elseif ($fromInput) {
            $query->where('incident_at', '>=', Carbon::parse($fromInput)->startOfDay());
        } elseif ($toInput) {
            $query->where('incident_at', '<=', Carbon::parse($toInput)->endOfDay());
        }

        if ($request->filled('country_id')) $query->where('country_id', $request->country_id);
        if ($request->filled('brand_id')) $query->where('brand_id', $request->brand_id);
        if ($request->filled('reported_by_user_id')) $query->where('created_by', $request->reported_by_user_id);

        return $query;
    }

    /**
     * ✅ EXPORT: Excel/PDF with selectable columns (uses buildIncidentQuery)
     * GET /api/incident-reports/export?format=xlsx&columns[]=incident_number&columns[]=division
     * GET /api/incident-reports/export?format=pdf&columns[]=incident_number&columns[]=latest_review_notes
     */
    public function export(Request $request)
    {
        $allowedColumns = $this->allowedExportColumns();

        /**
         * ✅ Tolerant: if UI sends other_incident_type, ignore it (do NOT 422).
         * (Also ensures it never appears as a column.)
         */
        $columnsIncoming = (array) $request->input('columns', []);
        $columnsIncoming = array_values(array_filter($columnsIncoming, fn ($c) => $c !== 'other_incident_type'));
        $request->merge(['columns' => $columnsIncoming]);

        $validator = Validator::make($request->all(), [
            'format' => ['nullable', Rule::in(['xlsx','pdf'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys($allowedColumns))],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $format  = strtolower((string)$request->input('format', 'xlsx'));
        $columns = $request->input('columns', []);

        // Default columns
        if (empty($columns)) {
            $columns = [
                'incident_number','division','location','country','incident_at','incident_type',
                'severity','status','reported_by_name','accused',
                'financial_loss','currency',
                'police_action_plan','date_insurance_claim_submitted',
                'latest_review_notes',

                // SLA columns (use correct keys)
                'sla_late_logged','sla_late_by','sla_close_urgency',

                // ✅ ALWAYS BOTTOM
                'robot_ranking',
            ];
        }

        $query = $this->buildIncidentQuery($request);

        // include relations needed for export
        $rows = $query->with(['attachments','brand','reportedBy','country','reportReviews'])
            ->latest('incident_at')
            ->get();

        // support both from/to and from_date/to_date
        $fromInput = $request->input('from', $request->input('from_date'));
        $toInput   = $request->input('to',   $request->input('to_date'));

        /**
         * ✅ Attach SLA presets + computed SLA values to each row
         */
        $incidentTypeNames = $rows->pluck('incident_type')->filter()->unique()->values();

        $typePresets = IncidentType::query()
            ->whereIn('name', $incidentTypeNames)
            ->get()
            ->keyBy('name');

        $now = Carbon::now();
        $closedStatuses = ['resolved','closed','rejected'];

        $rows->transform(function ($incident) use ($typePresets, $now, $closedStatuses) {
            $preset = $typePresets->get($incident->incident_type);

            $expectedResponse = (int) ($preset->expected_response_minutes ?? 0);
            $expectedClose    = (int) ($preset->expected_close_minutes ?? 0);

            $incidentAt = $incident->incident_at ? Carbon::parse($incident->incident_at) : null;
            $createdAt  = $incident->created_at  ? Carbon::parse($incident->created_at)  : null;

            // -------- LATE LOGGED (response) --------
            $responseDelayMinutes = null;
            $lateLogged = false;
            $lateByMinutes = null;

            if ($incidentAt && $createdAt) {
                $diff = $incidentAt->diffInMinutes($createdAt, false);
                $responseDelayMinutes = max(0, $diff < 0 ? 0 : $diff);

                if ($expectedResponse > 0 && $responseDelayMinutes > $expectedResponse) {
                    $lateLogged = true;
                    $lateByMinutes = $responseDelayMinutes - $expectedResponse;
                }
            }

            // -------- CLOSE SLA --------
            $isClosed = in_array((string)$incident->status, $closedStatuses, true);

            $minutesSinceCreated = null;
            $closeRemainingMinutes = null;
            $daysLeftToClose = null;
            $daysOverdue = null;

            if ($createdAt) {
                $minutesSinceCreated = $createdAt->diffInMinutes($now);

                if (!$isClosed && $expectedClose > 0) {
                    $closeRemainingMinutes = $expectedClose - $minutesSinceCreated;

                    if ($closeRemainingMinutes >= 0) {
                        $daysLeftToClose = (int) ceil($closeRemainingMinutes / 1440);
                        $daysOverdue = 0;
                    } else {
                        $daysLeftToClose = 0;
                        $daysOverdue = (int) ceil(abs($closeRemainingMinutes) / 1440);
                    }
                }
            }

            // ✅ Attach SLA into row so Excel export can read it
            $incident->sla = [
                'preset' => [
                    'expected_response_minutes' => $expectedResponse,
                    'expected_close_minutes' => $expectedClose,
                ],
                'response' => [
                    'response_delay_minutes' => $responseDelayMinutes,
                    'late_logged' => $lateLogged,
                    'late_by_minutes' => $lateByMinutes,
                ],
                'close' => [
                    'is_closed' => $isClosed,
                    'minutes_since_created' => $minutesSinceCreated,
                    'close_remaining_minutes' => $closeRemainingMinutes,
                    'days_left_to_close' => $daysLeftToClose,
                    'days_overdue' => $daysOverdue,
                ],
            ];

            return $incident;
        });

        $filenameBase = 'incident_reports_' . now()->format('Ymd_His');

        if ($format === 'xlsx') {
            return Excel::download(
                new IncidentReportsExport($rows, $columns, $allowedColumns, $fromInput, $toInput),
                $filenameBase . '.xlsx'
            );
        }

        // PDF (unchanged)
        $headings = array_map(fn($k) => $allowedColumns[$k] ?? $k, $columns);

        $pdf = PDF::loadView('riskdepertment::incident_reports', [
            'rows' => $rows,
            'columns' => $columns,
            'headings' => $headings,
            'allowedColumns' => $allowedColumns,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filenameBase . '.pdf');
    }

    /**
     * ✅ Allowed export columns (keys UI can select)
     * NOTE: other_incident_type REMOVED (now folded into incident_type as "Other (xxx)")
     */
    private function allowedExportColumns(): array
    {
        return [
            'incident_number' => 'Incident Number',
            'division' => 'Division',
            'location' => 'Location',
            'incident_at' => 'Incident Date/Time',
            'incident_type' => 'Incident Type',
            'severity' => 'Severity',
            'status' => 'Status of Incident (Robot ranking)',

            'reported_by_name' => 'Reported By',
            'reported_by_email' => 'Reporter Email',

            'accused' => 'Victim/Involved Party',
            'how_incident_picked' => 'How Incident Picked',

            'incident_summary' => 'Incident Summary',
            'root_cause' => 'Root Cause',
            'impact' => 'Impact',
            'immediate_action' => 'Immediate Action',
            'preventive_action' => 'Preventive Action',
            'management_comment' => 'Management Comment',

            'financial_loss' => 'Financial Loss',
            'amount_recovered' => 'Amount Recovered',
            'amount_unrecovered' => 'Amount Unrecovered',
            'currency' => 'Currency',
            'loss_still_happening' => 'Loss Still Happening',

            'police_required' => 'Police Required',
            'police_reported' => 'Reported To Police',
            'police_station' => 'Police Station',
            'police_case_number' => 'Police Case Number',
            'police_action_plan' => 'Police Plan',

            'attachments_count' => 'Attachments Count',
            'attachments_names' => 'Attachment Names',
            'date_insurance_claim_submitted' => 'Insurance Claim Submitted Date',
            'claim_number' => 'Insurance Claim Number',
            'submitted_by' => 'Claim Submitted By',

            'country' => 'Country',
            'brand' => 'Brand',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',

            // ✅ report_reviews export (latest only)
            'latest_review_status' => 'Latest Review Status',
            'latest_review_notes'  => 'Details and results of investigation',
            'latest_review_at'     => 'Latest Review Date',

            // SLA / Compliance
            'sla_late_logged'        => 'Late Logged',
            'sla_late_by'            => 'Late By',
            'sla_days_left_to_close' => 'Days Left To Close',
            'sla_close_urgency'      => 'Close Urgency',


            'robot_ranking' => 'Status of Incident (Robot ranking)',

        ];
    }


    /**
     * ✅ Escalate Incident (send PDF to multiple recipients)
     * Payload:
     *  - to:   array of emails (required, min 1)
     *  - cc:   array of emails (optional)
     *  - copy_reporter: boolean (optional)
     *  - level: string (optional)   e.g. "regional", "group_hq"
     *  - subject: string (optional)
     *  - body: string (optional)    (if not provided, default message is used)
     */
    public function escalateIncident(Request $request, string $id)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'escalate_incident', $site->id);
        if ($result !== true) {
            return $result;
        }

        // ✅ Validation (TO is DB-driven; request provides optional CC + copy_reporter)
        $validator = Validator::make($request->all(), [
            'cc' => ['nullable', 'array'],
            'cc.*' => ['nullable', 'email', 'max:255'],
            'copy_reporter' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        // ✅ Normalize + filter + unique
        $normalizeEmails = function ($arr) {
            $arr = is_array($arr) ? $arr : [];
            $arr = array_map(fn ($e) => strtolower(trim((string) $e)), $arr);
            $arr = array_filter($arr, fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL));
            return array_values(array_unique($arr));
        };

        // ✅ Recipients
        $toRecipients = $normalizeEmails(EmailsToEscalate::pluck('email')->toArray());
        $ccRecipients = $normalizeEmails($request->input('cc', []));

        if (count($toRecipients) < 1) {
            return response()->json([
                'status' => 422,
                'message' => 'At least one valid TO recipient is required.',
            ], 422);
        }

        $copyReporter = (bool) $request->input('copy_reporter', false);

        // ✅ Load incident
        $incident = IncidentReport::with([
            'reportReviews' => function ($query) {
                $query->with(['user' => function ($q) {
                    $q->with(['otherInfo']);
                }]);
            },
            'attachments',
            'brand',
            'reportedBy' => function ($query) {
                $query->with(['otherInfo']);
            },
            'country',
        ])
            ->where('site_id', $site->id)
            ->where('id', $id)
            ->firstOrFail();

        // ✅ Resolve reporter email
        $reporterEmail =
            $incident->reportedBy?->email
            ?? data_get($incident, 'reported_by.email')
            ?? null;

        // ✅ Sender email
        $fromUserEmail = trim((string) (auth()->user()?->email ?? ''));
        if ($fromUserEmail === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Logged-in user email not found (cannot send).',
            ], 422);
        }

        // ✅ Optional reporter CC
        if ($copyReporter && $reporterEmail && filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
            $rep = strtolower(trim((string) $reporterEmail));
            if (!in_array($rep, $toRecipients, true) && !in_array($rep, $ccRecipients, true)) {
                $ccRecipients[] = $rep;
            }
        }

        // ✅ Remove any overlap between TO and CC
        $ccRecipients = array_values(array_diff($ccRecipients, $toRecipients));

        /**
         * ✅ Render Blade -> HTML (PDF source)
         */
        $html = view('riskdepertment::incident_pdf', [
            'incident'    => $incident,
            'generatedAt' => now(),
            'site'        => $site,
        ])->render();

        // ✅ Generate PDF
        $pdfBytes = $this->renderPdfFromHtml($html, 'A4', 'portrait');

        $incidentNo = (string) ($incident->incident_number ?? $incident->id);
        $attachmentName = "Incident_{$incidentNo}_" . now()->format('Ymd_His') . ".pdf";

        // ✅ Subject/body (fixed + simple)
        $subject = "Incident Escalation ({$incidentNo})";
        $body =
            "Hi,\n\n" .
            "Incident {$incidentNo} has been escalated.\n" .
            "Please find the incident report attached.\n\n" .
            "Regards,\n" .
            (auth()->user()?->full_name ?? 'Simbisa Brands');

        // ✅ Safer debug logging (no raw recipient lists)
        Log::info('Escalation email prepared', [
            'incident_id' => $id,
            'from' => $fromUserEmail,
            'to_count' => count($toRecipients),
            'cc_count' => count($ccRecipients),
        ]);

        // ✅ Send Email via Graph
        $sent = $this->sendGraphEmailWithAttachmentDomainAware(
            fromUserEmail: $fromUserEmail,
            subject: $subject,
            body: $body,
            toRecipients: $toRecipients,
            ccRecipients: $ccRecipients,
            attachmentName: $attachmentName,
            attachmentBytes: $pdfBytes,
            attachmentMime: 'application/pdf'
        );

        if (!$sent) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to send email via Microsoft Graph.',
            ], 500);
        }

        return response()->json([
            'data' => $incident,
            'message' => 'Incident escalated and email sent.',
        ], 200);
    }





    public function assignInvestigator(Request $request, string $id)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'assign_investigator', $site->id);
        if ($result !== true) {
            return $result;
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'copy_reporter' => ['nullable', 'boolean'], // ✅ guard flag
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'data' => $validator->errors(),
            ], 422);
        }

        $investigatorEmail = trim((string) $request->input('email'));
        $copyReporter = (bool) $request->input('copy_reporter', false);

        // ✅ Load incident
        $incident = IncidentReport::with([
            'reportReviews' => function ($query) {
                $query->with(['user' => function ($q) {
                    $q->with(['otherInfo']);
                }]);
            },
            'attachments',
            'brand',
            'reportedBy' => function ($query) {
                $query->with(['otherInfo']);
            },
            'country',
        ])
            ->where('site_id', $site->id)
            ->where('id', $id)
            ->firstOrFail();

        // ✅ Resolve reporter email
        $reporterEmail =
            $incident->reportedBy?->email
            ?? data_get($incident, 'reported_by.email')
            ?? null;

        // ✅ Sender email
        $fromUserEmail = trim((string) (auth()->user()?->email ?? ''));

        if ($fromUserEmail === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Logged-in user email not found (cannot send).',
            ], 422);
        }

        /**
         * ✅ Render Blade -> HTML (PDF source)
         */
        $html = view('riskdepertment::incident_pdf', [
            'incident'    => $incident,
            'generatedAt' => now(),
            'site'        => $site,
        ])->render();

        // ✅ Generate PDF
        $pdfBytes = $this->renderPdfFromHtml($html, 'A4', 'portrait');

        $incidentNo = (string) ($incident->incident_number ?? $incident->id);
        $attachmentName = "Incident_{$incidentNo}_" . now()->format('Ymd_His') . ".pdf";

        // ✅ Email content
        $subject = "Incident Investigation Assigned ({$incidentNo})";
        $body =
            "Hi,\n\n" .
            "You have been assigned to investigate incident {$incidentNo}.\n" .
            "Please find the incident report attached.\n\n" .
            "Regards,\n" .
            (auth()->user()?->full_name ?? 'Simbisa Brands');

        /**
         * ✅ CC Guard Logic
         * Only CC reporter if:
         * - copy_reporter is true
         * - reporter email exists
         * - reporter is not same as investigator
         */
        $ccRecipients = [];

        if (
            $copyReporter &&
            $reporterEmail &&
            strtolower($reporterEmail) !== strtolower($investigatorEmail)
        ) {
            $ccRecipients[] = $reporterEmail;
        }

        // ✅ Send Email via Graph
        $sent = $this->sendGraphEmailWithAttachmentDomainAware(
            fromUserEmail: $fromUserEmail,
            subject: $subject,
            body: $body,
            toRecipients: [$investigatorEmail],
            ccRecipients: $ccRecipients,
            attachmentName: $attachmentName,
            attachmentBytes: $pdfBytes,
            attachmentMime: 'application/pdf'
        );

        if (!$sent) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to send email via Microsoft Graph.',
            ], 500);
        }

        return response()->json([
            'data' => $incident,
            'message' => 'Investigator assigned and email sent.',
        ], 200);
    }


    private function renderPdfFromHtml(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * ✅ Sends from the LOGGED IN user's mailbox (/users/{fromUserEmail}/sendMail)
     * ✅ Chooses tenant/app creds based on sender domain:
     *    - simbisa.com   => MSGRAPH_*2
     *    - otherwise     => MSGRAPH_*
     */

    private function sendGraphEmailWithAttachmentDomainAware(
        string $fromUserEmail,
        string $subject,
        string $body,
        array $toRecipients = [],
        array $ccRecipients = [],
        string $attachmentName = '',
        string $attachmentBytes = '',
        string $attachmentMime = 'application/octet-stream'
    ): bool {
        try {
            $accessToken = $this->getAccessTokenForDomain($fromUserEmail);

            // Clean recipients
            $toRecipients = collect($toRecipients)
                ->filter(fn($e) => is_string($e) && trim($e) !== '')
                ->map(fn($e) => trim($e))
                ->unique()
                ->values()
                ->toArray();

            $ccRecipients = collect($ccRecipients)
                ->filter(fn($e) => is_string($e) && trim($e) !== '')
                ->map(fn($e) => trim($e))
                ->unique()
                ->values()
                ->toArray();

            if (empty($toRecipients)) {
                Log::error("Graph sendMail blocked: empty toRecipients", ['from' => $fromUserEmail]);
                return false;
            }

            $message = [
                'subject' => (string) $subject,
                'body' => [
                    'contentType' => 'Text',   // or 'HTML'
                    'content' => (string) $body,
                ],
                'toRecipients' => collect($toRecipients)->map(fn ($addr) => [
                    'emailAddress' => ['address' => $addr],
                ])->values()->toArray(),
            ];

            if (!empty($ccRecipients)) {
                $message['ccRecipients'] = collect($ccRecipients)->map(fn ($addr) => [
                    'emailAddress' => ['address' => $addr],
                ])->values()->toArray();
            }

            // ✅ Only include attachment when valid
            $hasAttachment = trim((string)$attachmentName) !== '' && $attachmentBytes !== '';
            if ($hasAttachment) {
                $message['attachments'] = [
                    [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'name' => $attachmentName,
                        'contentType' => $attachmentMime ?: 'application/octet-stream',
                        'contentBytes' => base64_encode($attachmentBytes),
                    ],
                ];
            }

            $payload = [
                'message' => $message,
                'saveToSentItems' => true,
            ];

            $response = Http::withToken($accessToken)
                ->post("https://graph.microsoft.com/v1.0/users/{$fromUserEmail}/sendMail", $payload);

            if ($response->failed()) {
                Log::error("Graph sendMail failed", [
                    'from' => $fromUserEmail,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'debug' => [
                        'to_count' => count($toRecipients),
                        'cc_count' => count($ccRecipients),
                        'has_attachment' => $hasAttachment,
                        'attachment_name' => $attachmentName,
                    ],
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("Microsoft Graph email failed: " . $e->getMessage(), [
                'from' => $fromUserEmail,
            ]);
            return false;
        }
    }

    private function getAccessTokenForDomain(string $fromAddress): string
    {
        $fromAddress = strtolower(trim($fromAddress));
        $domain = substr(strrchr($fromAddress, "@"), 1) ?: "";

        // Route by domain suffix
        $isComTenant  = str_ends_with($domain, 'simbisa.com');   // matches zw-simbisa.com, simbisa.com, *.simbisa.com
        $isCoZwTenant = str_ends_with($domain, 'simbisa.co.zw'); // matches simbisa.co.zw, *.simbisa.co.zw

        $suffix = match (true) {
            $isCoZwTenant => '',    // primary
            $isComTenant  => '2',   // tenant 2
            default => throw new \Exception("Unsupported FROM domain: {$domain}"),
        };

        $tenantId     = env("MSGRAPH_TENANT_ID{$suffix}");
        $clientId     = env("MSGRAPH_CLIENT_ID{$suffix}");
        $clientSecret = env("MSGRAPH_CLIENT_SECRET{$suffix}");
        ///dd($fromAddress,$tenantId, $clientId, $clientSecret);

        if (!$tenantId || !$clientId || !$clientSecret) {
            throw new \Exception("Missing MSGRAPH_* env vars for domain {$domain} (suffix '{$suffix}')");
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()->post($url, [
            'client_id' => $clientId,
            'scope' => 'https://graph.microsoft.com/.default',
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new \Exception("Failed to get access token: " . $response->body());
        }

        $json = $response->json();
        if (!isset($json['access_token'])) {
            throw new \Exception("Access token missing in response: " . $response->body());
        }

        return $json['access_token'];
    }






    public function downloadSinglePdf(Request $request, string $id)
    {
        $site = app('site');

        $result = hasPermission(auth()->user()->role_id, 'download_report', $site->id);
        if ($result !== true) return $result;

        $incident = IncidentReport::with([
            'reportReviews' => function ($query) {
                $query->with(['user' => function ($q) {
                    $q->with(['otherInfo']);
                }]);
            },
            'attachments',
            'brand',
            'reportedBy' => function ($query) {
                $query->with(['otherInfo']);
            },
            'country',
        ])
            ->where('site_id', $site->id)
            ->where('id', $id)
            ->firstOrFail();

        $html = view('riskdepertment::incident_pdf', [
            'incident'    => $incident,
            'generatedAt' => now(),
            'site'        => $site,
        ])->render();

        // ✅ Quick sanity: if Blade renders empty, PDF will be blank
        if (trim(strip_tags($html)) === '') {
            return response()->json([
                'status' => 500,
                'message' => 'PDF template rendered empty HTML (incident_pdf).',
            ], 500);
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);          // allow http/https assets
        $options->set('defaultFont', 'DejaVu Sans');     // safe font
        $options->set('tempDir', storage_path('app'));   // avoid temp permission issues

        $dompdf = new Dompdf($options);

        // ✅ Important for relative assets like /images/logo.png
        $dompdf->setBasePath(public_path());

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfBytes = $dompdf->output();

        $incidentNo = (string) ($incident->incident_number ?? $incident->id);
        $filename = "Incident_{$incidentNo}_" . now()->format('Ymd_His') . ".pdf";

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'              => 'no-cache',
        ]);
    }













}


