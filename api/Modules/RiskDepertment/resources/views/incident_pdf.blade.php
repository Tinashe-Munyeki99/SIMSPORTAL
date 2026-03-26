{{-- resources/views/vendor/riskdepertment/incident_pdf.blade.php --}}
    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Report</title>
    <style>
        @page {
            size: A4;
            margin: 6mm;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #103b73;
            background: #d9e3ea;
            margin: 0;
            padding: 0;
        }

        .page {
            background: #f3f3f3;
            padding: 14px 16px 16px;
            min-height: 100%;
            page-break-after: always;
            margin: 0;
            box-sizing: border-box;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .title {
            text-align: center;
            font-size: 22px;
            font-weight: 800;
            color: #082b75;
            margin: 0 0 12px;
        }

        .section-title {
            background: #082b75;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 7px 10px;
            margin: 12px 0 8px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .detail-table td {
            padding: 4px 5px;
            vertical-align: top;
        }

        .label-cell {
            width: 30%;
            color: #103b73;
            font-weight: 600;
        }

        .colon-cell {
            width: 3%;
            text-align: center;
            font-weight: bold;
            color: #103b73;
        }

        .value-cell {
            width: 67%;
        }

        .field-box {
            background: #e8e8e8;
            min-height: 20px;
            padding: 5px 7px;
            color: #1e2a44;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.35;
        }

        .field-box.large {
            min-height: 48px;
        }

        .field-box.xlarge {
            min-height: 66px;
        }

        .review-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
            table-layout: fixed;
        }

        .review-table th {
            background: #082b75;
            color: #ffffff;
            text-align: left;
            padding: 7px 9px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 800;
        }

        .review-table td {
            background: #e8e8e8;
            color: #1e2a44;
            padding: 7px 9px;
            vertical-align: top;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .small {
            font-size: 10px;
        }

        .money {
            font-size: 12px;
            font-weight: 800;
            color: #082b75;
            letter-spacing: 0.2px;
        }

        .money-line {
            line-height: 1.5;
        }

        .avoid-break {
            page-break-inside: avoid;
        }

        .review-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .review-table th {
            background: #082b75;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 800;
            border: 1px solid #d7dce2;
        }

        .review-table td {
            background: #e8e8e8;
            color: #1e2a44;
            padding: 8px 10px;
            vertical-align: top;
            border: 1px solid #d7dce2;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .reviewer-cell {
            line-height: 1.35;
        }

        .reviewer-name {
            display: block;
            font-weight: 700;
            color: #082b75;
            margin-bottom: 3px;
        }

        .reviewer-email {
            display: block;
            font-size: 10px;
            color: #4b5563;
            word-break: break-all;
        }

        .review-date {
            white-space: nowrap;
            font-size: 11px;
        }

        .review-status {
            font-weight: 700;
            text-transform: capitalize;
        }

        .review-notes {
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.4;
        }
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

    $formatMoney = function ($amount, $currency = '') {
        if ($amount === null || $amount === '') {
            return '—';
        }

        if (is_numeric($amount)) {
            $formatted = number_format((float) $amount, 2);
            return trim(($currency ? $currency . ' ' : '') . $formatted);
        }

        return trim(($currency ? $currency . ' ' : '') . $amount);
    };

    $financialLossFormatted = $formatMoney($financialLoss, $currency);
    $amountRecoveredFormatted = $formatMoney($amountRecovered, $currency);
    $amountUnrecoveredFormatted = $formatMoney($amountUnrecovered, $currency);
@endphp

<div class="page">
    <div class="title">Incident Report</div>

    <div class="section-title">Incident Details</div>
    <table class="detail-table avoid-break">
        <tr>
            <td class="label-cell">Incident Number</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $incidentNo ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Generated At</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $generatedAt ?? now() }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Division Name</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $division ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Shop / Location</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $location ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Country</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $country ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Brand</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $brand ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Date &amp; Time of Incident</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $incidentAt ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Incident Type</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $incidentType ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Status</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $status ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Severity</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $severity ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">How Incident Picked</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $howPicked ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Victim / Involved Party</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $accused ?: '—' }}</div></td>
        </tr>
    </table>

    <div class="section-title">Reported By</div>
    <table class="detail-table avoid-break">
        <tr>
            <td class="label-cell">Full Name</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $reportedName ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Email</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $reportedEmail ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Phone</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $reportedPhone ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Created / Updated</td>
            <td class="colon-cell">:</td>
            <td class="value-cell">
                <div class="field-box">Created: {{ $createdAt ?: '—' }}
                    Updated: {{ $updatedAt ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Narrative</div>
    <table class="detail-table">
        <tr>
            <td class="label-cell">Incident Summary</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box large">{{ $summary ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Root Cause</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box large">{{ $rootCause ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Impact</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box large">{{ $impact ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Immediate Action</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box large">{{ $immediate ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Preventive Action</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box large">{{ $preventive ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Management Comment</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box xlarge">{{ $management ?: '—' }}</div></td>
        </tr>
    </table>

    <div class="section-title">Financial &amp; Police</div>
    <table class="detail-table avoid-break">
        <tr>
            <td class="label-cell">Loss Still Happening</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $lossStill }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Currency</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $currency ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Financial Loss</td>
            <td class="colon-cell">:</td>
            <td class="value-cell">
                <div class="field-box money">{{ $financialLossFormatted }}</div>
            </td>
        </tr>
        <tr>
            <td class="label-cell">Recovered / Unrecovered</td>
            <td class="colon-cell">:</td>
            <td class="value-cell">
                <div class="field-box money-line">
                    <strong>Recovered:</strong> <span class="money">{{ $amountRecoveredFormatted }}</span><br>
                    <strong>Unrecovered:</strong> <span class="money">{{ $amountUnrecoveredFormatted }}</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="label-cell">Police Required</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $policeRequired }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Reported To Police</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $policeReported }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Police Station</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $policeStation ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Police Case Number</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $policeCaseNo ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Police Action Plan</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box large">{{ $policePlan ?: '—' }}</div></td>
        </tr>
        <tr>
            <td class="label-cell">Insurance Claim Submitted Date</td>
            <td class="colon-cell">:</td>
            <td class="value-cell"><div class="field-box">{{ $insuranceDate ?: '—' }}</div></td>
        </tr>
    </table>
</div>

<div class="page">
    <div class="title">Reviews</div>

    <div class="section-title">Review History</div>

    @php
        $reviews = collect($incident->reportReviews ?? []);
    @endphp

    @if($reviews->isEmpty())
        <table class="detail-table">
            <tr>
                <td class="label-cell">Message</td>
                <td class="colon-cell">:</td>
                <td class="value-cell">
                    <div class="field-box">No reviews found.</div>
                </td>
            </tr>
        </table>
    @else
        <table class="review-table">
            <tr>
                <th style="width: 16%;">Date</th>
                <th style="width: 16%;">Status</th>
                <th style="width: 28%;">Reviewer</th>
                <th style="width: 40%;">Notes</th>
            </tr>

            @foreach($reviews as $rv)
                @php
                    $rvUserName = data_get($rv, 'user.full_name') ?? data_get($rv, 'user.name') ?? '—';
                    $rvUserEmail = data_get($rv, 'user.email') ?? '';
                    $rvStatus = $rv->status ?? '—';
                    $rvAt = $rv->created_at
                        ? \Carbon\Carbon::parse($rv->created_at)->format('d M Y H:i')
                        : '—';
                    $rvNotes = $rv->notes ?? '—';
                @endphp

                <tr>
                    <td class="review-date">{{ $rvAt }}</td>

                    <td class="review-status">{{ $rvStatus }}</td>

                    <td class="reviewer-cell">
                        <span class="reviewer-name">{{ $rvUserName }}</span>

                        @if($rvUserEmail)
                            <span class="reviewer-email">{{ $rvUserEmail }}</span>
                        @endif
                    </td>

                    <td class="review-notes">{{ $rvNotes }}</td>
                </tr>
            @endforeach
        </table>
    @endif

</div>

</body>
</html>

