<?php

namespace Modules\RiskDepertment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\RiskDepertment\Models\IncidentNotificationReceipient;
use Modules\RiskDepertment\Models\IncidentNotificationRule;

class IncidentNotificationRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // LIST rules (with recipients)
    public function index(Request $request)
    {
        $query = IncidentNotificationRule::query()
            ->with(['recipients' => function ($q) {
                $q->select('id', 'rule_id', 'email', 'deleted_at', 'created_at', 'updated_at');
            }]);

        // Optional filters
        if ($request->filled('currency')) {
            $query->where('currency', strtoupper($request->string('currency')));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $rules = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($rules);
    }

    // CREATE rule + recipients
    public function store(Request $request)
    {
        $data = $this->validateRulePayload($request);

        return DB::transaction(function () use ($data) {
            $rule = IncidentNotificationRule::create([
                'currency'   => strtoupper($data['currency']),
                'min_amount' => $data['min_amount'],
                'max_amount' => $data['max_amount'] ?? null,
                'is_active'  => $data['is_active'] ?? true,
            ]);

            $emails = collect($data['recipients'] ?? [])
                ->map(fn($e) => strtolower(trim($e)))
                ->unique()
                ->values();

            foreach ($emails as $email) {
                IncidentNotificationReceipient::create([
                    'rule_id' => $rule->id,
                    'email'   => $email,
                ]);
            }

            return response()->json($rule->load('recipients'), 201);
        });
    }

    // UPDATE rule + replace recipients (simple & reliable)
    public function update(Request $request, string $id)
    {
        $data = $this->validateRulePayload($request, $id);

        return DB::transaction(function () use ($data, $id) {
            $rule = IncidentNotificationRule::with('recipients')->findOrFail($id);

            $rule->update([
                'currency'   => strtoupper($data['currency']),
                'min_amount' => $data['min_amount'],
                'max_amount' => $data['max_amount'] ?? null,
                'is_active'  => $data['is_active'] ?? $rule->is_active,
            ]);

            if (array_key_exists('recipients', $data)) {
                // soft-delete existing recipients then recreate (replace semantics)
                $rule->recipients()->delete();

                $emails = collect($data['recipients'] ?? [])
                    ->map(fn($e) => strtolower(trim($e)))
                    ->unique()
                    ->values();

                foreach ($emails as $email) {
                    IncidentNotificationReceipient::create([
                        'rule_id' => $rule->id,
                        'email'   => $email,
                    ]);
                }
            }

            return response()->json($rule->fresh()->load('recipients'));
        });
    }

    // DELETE rule (soft delete; recipients also deleted if FK cascade hard deletes, so we do it explicitly)
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $rule = IncidentNotificationRule::findOrFail($id);

            // Soft-delete recipients first (keeps audit trail consistent)
            $rule->recipients()->delete();

            $rule->delete();

            return response()->json(['message' => 'Rule deleted']);
        });
    }

    private function validateRulePayload(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount'],
            'is_active' => ['sometimes', 'boolean'],

            // recipients are optional on update; required on create if you want—change as needed
            'recipients' => ['sometimes', 'array'],
            'recipients.*' => ['required', 'email'],
        ]);
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


    /**
     * Remove the specified resource from storage.
     */

}
