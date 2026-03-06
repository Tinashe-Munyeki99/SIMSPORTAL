
{{-- resources/views/vendor/riskdepertment/incident_pdf.blade.php --}}
    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Report</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #000; }

        /* Typography */
        .title {
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 8px 0;
        }
        .subtitle {
            font-size: 11px;
            margin: 0;
        }

        /* Boxes & layout */
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        .header-box {
            border: 2px solid #000;
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .section-box {
            border: 2px solid #000;
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 8px 0;
            font-size: 12px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.grid td, table.grid th {
            border: 2px solid #000;
            padding: 6px 7px;
            vertical-align: top;
        }
        table.grid th {
            text-align: left;
            text-transform: uppercase;
            font-weight: 800;
        }

        .label {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 10px;
            margin: 0 0 2px 0;
        }
        .value {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .two-col { width: 100%; border-collapse: collapse; }
        .two-col td { width: 50%; padding-right: 8px; vertical-align: top; }
        .two-col td:last-child { padding-right: 0; }

        .small {
            font-size: 10px;
        }

        .muted { color: #111; }

        .footer-note {
            margin-top: 8px;
            font-size: 10px;
        }

        /* Keep a box together (avoid splitting key blocks) */
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>

@php
    $incidentNo   = $incident->incident_number ?? $incident->id;
    $brand        = $incident->brand->name ?? '';
    $country      = $incident->country->name ?? '';
    $incidentAt   = $incident->incident_at ?? '';
    $division     = $incident->division ?? '';
    $location     = $incident->location ?? '';
    $status       = $incident->status ?? '';
    $severity     = $incident->severity ?? '';

    $reportedName = $incident->reportedBy->full_name
        ?? data_get($incident, 'reported_by.full_name')
        ?? '';

    $reportedEmail = $incident->reportedBy->email
        ?? data_get($incident, 'reported_by.email')
        ?? '';

    $reportedPhone = data_get($incident, 'reportedBy.otherInfo.phone')
        ?? data_get($incident, 'reported_by.other_info.phone')
        ?? '';

    $howPicked = $incident->how_incident_picked ?? '';
    $accused   = $incident->accused ?? '';

    $incidentType = $incident->incident_type ?? '';
    $otherType = $incident->other_incident_type
        ?? $incident->incident_type_other
        ?? '';

    if (strtolower(trim($incidentType)) === 'other' && trim((string)$otherType) !== '') {
        $incidentType = "Other ({$otherType})";
    }

    $lossStill = (int)($incident->loss_still_happening ?? 0) === 1 ? 'Yes' : 'No';
    $financialLoss = $incident->financial_loss ?? '';
    $amountRecovered = $incident->amount_recovered ?? '';
    $amountUnrecovered = $incident->amount_unrecovered ?? '';
    $currency = $incident->currency ?? '';

    $policeRequired = (int)($incident->police_required ?? 0) === 1 ? 'Yes' : 'No';
    $policeReported = (int)($incident->police_reported ?? 0) === 1 ? 'Yes' : 'No';
    $policeStation  = $incident->police_station ?? '';
    $policeCaseNo   = $incident->police_case_number ?? '';
    $policePlan     = $incident->police_action_plan ?? '';

    $insuranceDate  = $incident->date_insurance_claim_submitted ?? '';

    $summary = $incident->incident_summary ?? '';
    $rootCause = $incident->root_cause ?? '';
    $impact = $incident->impact ?? '';
    $immediate = $incident->immediate_action ?? '';
    $preventive = $incident->preventive_action ?? '';
    $management = $incident->management_comment ?? '';

    $createdAt = $incident->created_at ?? '';
    $updatedAt = $incident->updated_at ?? '';
@endphp

{{-- ======================================================
     PAGE 1: INCIDENT DETAILS
====================================================== --}}
<div class="page">

    <div class="header-box avoid-break">
        <p class="title">Incident Report</p>
        <p class="subtitle"><strong>Incident Number:</strong> {{ $incidentNo }}</p>
        <p class="subtitle"><strong>Generated At:</strong> {{ $generatedAt ?? now() }}</p>
    </div>

    <div class="section-box avoid-break">
        <p class="section-title">Incident Details</p>

        <table class="grid">
            <tr>
                <td>
                    <p class="label">Division Name</p>
                    <p class="value">{{ $division ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Shop / Location</p>
                    <p class="value">{{ $location ?: '—' }}</p>
                </td>
            </tr>

            <tr>
                <td>
                    <p class="label">Country</p>
                    <p class="value">{{ $country ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Brand</p>
                    <p class="value">{{ $brand ?: '—' }}</p>
                </td>
            </tr>

            <tr>
                <td>
                    <p class="label">Date &amp; Time of Incident</p>
                    <p class="value">{{ $incidentAt ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Incident Type</p>
                    <p class="value">{{ $incidentType ?: '—' }}</p>
                </td>
            </tr>

            <tr>
                <td>
                    <p class="label">Status</p>
                    <p class="value">{{ $status ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Severity</p>
                    <p class="value">{{ $severity ?: '—' }}</p>
                </td>
            </tr>

            <tr>
                <td>
                    <p class="label">How Incident Picked</p>
                    <p class="value">{{ $howPicked ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Victim / Involved Party</p>
                    <p class="value">{{ $accused ?: '—' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-box avoid-break">
        <p class="section-title">Reported By</p>

        <table class="grid">
            <tr>
                <td>
                    <p class="label">Full Name</p>
                    <p class="value">{{ $reportedName ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Email</p>
                    <p class="value">{{ $reportedEmail ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Phone</p>
                    <p class="value">{{ $reportedPhone ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Created / Updated</p>
                    <p class="value">Created: {{ $createdAt ?: '—' }}&#10;Updated: {{ $updatedAt ?: '—' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-box">
        <p class="section-title">Narrative</p>

        <table class="grid">
            <tr>
                <td>
                    <p class="label">Incident Summary</p>
                    <p class="value">{{ $summary ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Root Cause</p>
                    <p class="value">{{ $rootCause ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Impact</p>
                    <p class="value">{{ $impact ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Immediate Action</p>
                    <p class="value">{{ $immediate ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Preventive Action</p>
                    <p class="value">{{ $preventive ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Management Comment</p>
                    <p class="value">{{ $management ?: '—' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-box avoid-break">
        <p class="section-title">Financial &amp; Police</p>

        <table class="grid">
            <tr>
                <td>
                    <p class="label">Loss Still Happening</p>
                    <p class="value">{{ $lossStill }}</p>
                </td>
                <td>
                    <p class="label">Currency</p>
                    <p class="value">{{ $currency ?: '—' }}</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="label">Financial Loss</p>
                    <p class="value">{{ $financialLoss !== '' ? $financialLoss : '—' }}</p>
                </td>
                <td>
                    <p class="label">Amount Recovered / Unrecovered</p>
                    <p class="value">
                        Recovered: {{ $amountRecovered !== '' ? $amountRecovered : '—' }}&#10;
                        Unrecovered: {{ $amountUnrecovered !== '' ? $amountUnrecovered : '—' }}
                    </p>
                </td>
            </tr>

            <tr>
                <td>
                    <p class="label">Police Required</p>
                    <p class="value">{{ $policeRequired }}</p>
                </td>
                <td>
                    <p class="label">Reported To Police</p>
                    <p class="value">{{ $policeReported }}</p>
                </td>
            </tr>

            <tr>
                <td>
                    <p class="label">Police Station</p>
                    <p class="value">{{ $policeStation ?: '—' }}</p>
                </td>
                <td>
                    <p class="label">Police Case Number</p>
                    <p class="value">{{ $policeCaseNo ?: '—' }}</p>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <p class="label">Police Action Plan</p>
                    <p class="value">{{ $policePlan ?: '—' }}</p>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <p class="label">Insurance Claim Submitted Date</p>
                    <p class="value">{{ $insuranceDate ?: '—' }}</p>
                </td>
            </tr>
        </table>

        <p class="footer-note muted">
            This report is system generated. Please verify details and proceed with investigation as required.
        </p>
    </div>
</div>


{{-- ======================================================
     PAGE 2: REVIEWS
====================================================== --}}
<div class="page">
    <div class="header-box avoid-break">
        <p class="title">Reviews</p>
        <p class="subtitle"><strong>Incident Number:</strong> {{ $incidentNo }}</p>
    </div>

    <div class="section-box">
        <p class="section-title">Review History</p>

        @php
            $reviews = collect($incident->reportReviews ?? []);
        @endphp

        @if($reviews->isEmpty())
            <table class="grid">
                <tr>
                    <td>
                        <p class="label">Message</p>
                        <p class="value">No reviews found.</p>
                    </td>
                </tr>
            </table>
        @else
            <table class="grid">
                <tr>
                    <th style="width: 18%;">Date</th>
                    <th style="width: 18%;">Status</th>
                    <th style="width: 24%;">Reviewer</th>
                    <th>Notes</th>
                </tr>

                @foreach($reviews as $rv)
                    @php
                        $rvUserName = data_get($rv, 'user.full_name') ?? data_get($rv, 'user.name') ?? '—';
                        $rvUserEmail = data_get($rv, 'user.email') ?? '';
                        $rvStatus = $rv->status ?? '—';
                        $rvAt = $rv->created_at ?? '—';
                        $rvNotes = $rv->notes ?? '—';
                    @endphp
                    <tr>
                        <td>{{ $rvAt }}</td>
                        <td>{{ $rvStatus }}</td>
                        <td>
                            {{ $rvUserName }}
                            @if($rvUserEmail) <br><span class="small">{{ $rvUserEmail }}</span> @endif
                        </td>
                        <td style="white-space: pre-wrap;">{{ $rvNotes }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</div>


{{-- ======================================================
     PAGE 3: ATTACHMENTS
====================================================== --}}
{{--<div class="page">--}}
{{--    <div class="header-box avoid-break">--}}
{{--        <p class="title">Attachments</p>--}}
{{--        <p class="subtitle"><strong>Incident Number:</strong> {{ $incidentNo }}</p>--}}
{{--    </div>--}}

{{--    <div class="section-box">--}}
{{--        <p class="section-title">Files</p>--}}

{{--        @php--}}
{{--            $atts = collect($incident->attachments ?? []);--}}
{{--        @endphp--}}

{{--        @if($atts->isEmpty())--}}
{{--            <table class="grid">--}}
{{--                <tr>--}}
{{--                    <td>--}}
{{--                        <p class="label">Message</p>--}}
{{--                        <p class="value">No attachments found.</p>--}}
{{--                    </td>--}}
{{--                </tr>--}}
{{--            </table>--}}
{{--        @else--}}
{{--            <table class="grid">--}}
{{--                <tr>--}}
{{--                    <th style="width: 15%;">#</th>--}}
{{--                    <th>File Name</th>--}}
{{--                </tr>--}}

{{--                @foreach($atts as $i => $a)--}}
{{--                    @php--}}
{{--                        $fileName = $a->file_name ?? $a->name ?? '—';--}}
{{--                    @endphp--}}
{{--                    <tr>--}}
{{--                        <td>{{ $i + 1 }}</td>--}}
{{--                        <td style="white-space: pre-wrap;">{{ $fileName }}</td>--}}
{{--                    </tr>--}}
{{--                @endforeach--}}
{{--            </table>--}}
{{--        @endif--}}
{{--    </div>--}}
{{--</div>--}}

</body>
</html>
