{{-- riskdepertment::incident_reports --}}
    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Reports Export</title>
    <style>
        @page { margin: 18px; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #1E2A44;
        }

        .header {
            margin-bottom: 10px;
            display: table;
            width: 100%;
        }
        .header .left { display: table-cell; vertical-align: bottom; }
        .header .right { display: table-cell; text-align: right; vertical-align: bottom; color:#6B7A99; }

        h1 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .5px;
        }

        .meta {
            margin-top: 3px;
            color: #6B7A99;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            background: #F5F7FB;
            border: 1px solid #D8DEEC;
            padding: 6px 6px;
            text-align: left;
            font-weight: 800;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .4px;
            word-wrap: break-word;
        }

        tbody td {
            border: 1px solid #EEF2F8;
            padding: 6px 6px;
            vertical-align: top;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        tbody tr:nth-child(even) {
            background: #FBFCFF;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #D8DEEC;
            font-weight: 800;
            font-size: 9px;
        }

        .muted { color: #6B7A99; }

        .footer {
            margin-top: 10px;
            color: #6B7A99;
            font-size: 9px;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="left">
        <h1>Incident Reports Export</h1>
        <div class="meta">
            Records: {{ is_countable($rows) ? count($rows) : 0 }}
        </div>
    </div>
    <div class="right">
        Generated: {{ isset($generatedAt) ? $generatedAt->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s') }}
    </div>
</div>

<table>
    <thead>
    <tr>
        @foreach($headings as $h)
            <th>{{ $h }}</th>
        @endforeach
    </tr>
    </thead>

    <tbody>
    @forelse($rows as $row)
        <tr>
            @foreach($columns as $colKey)
                <td>
                    @php
                        // helper locals
                        $val = null;

                        $attachments = is_array($row->attachments ?? null) ? $row->attachments : ($row->attachments ?? collect());
                        $attachmentsCount = is_countable($attachments) ? count($attachments) : 0;

                        $attachmentNames = [];
                        if (is_iterable($attachments)) {
                            foreach ($attachments as $a) {
                                if (!empty($a->file_name)) $attachmentNames[] = $a->file_name;
                            }
                        }

                        // latest review
                        $rr = $row->reportReviews ?? $row->report_reviews ?? [];
                        $latestReview = null;

                        if ($rr instanceof \Illuminate\Support\Collection) {
                            $latestReview = $rr->sortBy('created_at')->last();
                        } elseif (is_array($rr)) {
                            // if already sorted by backend, last is ok; otherwise sort safely:
                            usort($rr, function($a,$b){
                                $at = strtotime($a['created_at'] ?? $a->created_at ?? '0');
                                $bt = strtotime($b['created_at'] ?? $b->created_at ?? '0');
                                return $at <=> $bt;
                            });
                            $latestReview = end($rr) ?: null;
                        }

                        $latestReviewStatus = is_array($latestReview) ? ($latestReview['status'] ?? null) : ($latestReview->status ?? null);
                        $latestReviewNotes  = is_array($latestReview) ? ($latestReview['notes'] ?? null)  : ($latestReview->notes ?? null);
                        $latestReviewAt     = is_array($latestReview) ? ($latestReview['created_at'] ?? null) : ($latestReview->created_at ?? null);

                        // switch for each export column key
                        switch ($colKey) {
                            case 'incident_number': $val = $row->incident_number ?? null; break;
                            case 'division': $val = $row->division ?? null; break;
                            case 'location': $val = $row->location ?? null; break;
                            case 'incident_at': $val = $row->incident_at ?? null; break;

                            case 'incident_type': $val = $row->incident_type ?? null; break;
                            case 'other_incident_type': $val = $row->other_incident_type ?? null; break;

                            case 'severity': $val = $row->severity ?? null; break;
                            case 'status': $val = $row->status ?? null; break;

                            case 'reported_by_name':
                                // your API returns reported_by as relationship
                                $val = $row->reportedBy->full_name ?? $row->reported_by->full_name ?? $row->reported_by_name ?? $row->reported_by ?? null;
                                break;

                            case 'reported_by_email':
                                $val = $row->reportedBy->email ?? $row->reported_by->email ?? $row->reported_by_email ?? null;
                                break;

                            case 'accused': $val = $row->accused ?? null; break;
                            case 'how_incident_picked': $val = $row->how_incident_picked ?? null; break;

                            case 'incident_summary': $val = $row->incident_summary ?? null; break;
                            case 'root_cause': $val = $row->root_cause ?? null; break;
                            case 'impact': $val = $row->impact ?? null; break;

                            case 'immediate_action': $val = $row->immediate_action ?? null; break;
                            case 'preventive_action': $val = $row->preventive_action ?? null; break;
                            case 'management_comment': $val = $row->management_comment ?? null; break;

                            case 'financial_loss': $val = $row->financial_loss ?? null; break;
                            case 'amount_recovered': $val = $row->amount_recovered ?? null; break;
                            case 'amount_unrecovered': $val = $row->amount_unrecovered ?? null; break;
                            case 'currency': $val = $row->currency ?? null; break;

                            case 'loss_still_happening':
                                $val = ((int)($row->loss_still_happening ?? 0) === 1) ? 'Yes' : 'No';
                                break;

                            case 'police_required':
                                $val = ((int)($row->police_required ?? 0) === 1) ? 'Yes' : 'No';
                                break;

                            case 'police_reported':
                                $val = ((int)($row->police_reported ?? 0) === 1) ? 'Yes' : 'No';
                                break;

                            case 'police_station': $val = $row->police_station ?? null; break;
                            case 'police_case_number': $val = $row->police_case_number ?? null; break;

                            case 'attachments_count': $val = $attachmentsCount; break;
                            case 'attachments_names': $val = implode(', ', $attachmentNames); break;

                            case 'country':
                                $val = $row->country->name ?? $row->country_name ?? null;
                                break;

                            case 'brand':
                                $val = $row->brand->name ?? $row->brand_name ?? null;
                                break;

                            case 'created_at': $val = $row->created_at ?? null; break;
                            case 'updated_at': $val = $row->updated_at ?? null; break;

                            case 'latest_review_status': $val = $latestReviewStatus; break;
                            case 'latest_review_notes':  $val = $latestReviewNotes; break;
                            case 'latest_review_at':     $val = $latestReviewAt; break;

                            default:
                                // fallback: attempt property access
                                $val = $row->{$colKey} ?? null;
                                break;
                        }
                    @endphp

                    @if($colKey === 'status' && !empty($val))
                        <span class="badge">{{ $val }}</span>
                    @elseif($colKey === 'severity' && !empty($val))
                        <span class="badge">{{ $val }}</span>
                    @else
                        {{ $val !== null && $val !== '' ? $val : '—' }}
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($headings) }}" class="muted">
                No incident reports found for the selected filters.
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

<div class="footer">
    Exported from Incident Reports • {{ now()->format('Y-m-d') }}
</div>
</body>
</html>
