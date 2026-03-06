<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncidentReportsExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithEvents,
    WithColumnWidths
{
    private Collection $rows;
    private array $columns;
    private array $allowed;

    private ?string $fromInput;
    private ?string $toInput;

    private int $dueSoonDays = 7;

    // ✅ where real data ends (so borders stop there)
    private int $dataEndRow = 2;

    // ✅ where key starts (below table)
    private int $keyStartRow = 0;

    public function __construct($rows, array $columns, array $allowedColumns, $fromInput = null, $toInput = null)
    {
        $this->rows = $rows instanceof Collection ? $rows : collect($rows);

        // ✅ remove other_incident_type if it slips through
        $this->columns = array_values(array_filter($columns, fn ($c) => $c !== 'other_incident_type'));

        $this->allowed = $allowedColumns;

        $this->fromInput = $fromInput ? (string)$fromInput : null;
        $this->toInput   = $toInput ? (string)$toInput : null;
    }

    public function headings(): array
    {
        return [];
    }

    public function array(): array
    {
        $title = $this->buildTitleLine();

        // ✅ uppercase headings
        $headings = array_map(fn ($k) => strtoupper($this->allowed[$k] ?? $k), $this->columns);

        $dataRows = $this->rows->map(function ($r) {
            return array_map(fn ($key) => $this->valueFor($r, $key), $this->columns);
        })->toArray();

        // ✅ Row 1 title, Row 2 headings, data starts row 3
        $this->dataEndRow = 2 + count($dataRows);

        // ✅ Key starts after 2 blank lines (like sample)
        $this->keyStartRow = $this->dataEndRow + 3;

        // ✅ KEY block uses 4 columns (A-D) and sits BELOW the bordered table
        // Layout:
        // Row KEY:  A:D merged "KEY"
        // Row 1:    A color | B text | C color | D text
        // Row 2:    A color | B text | C empty | D empty
        $keyRows = [
            // blank row 1
            array_fill(0, max(1, count($this->columns)), null),

            // blank row 2
            array_fill(0, max(1, count($this->columns)), null),

            // KEY title row (put in col A, we will merge A:D in styling)
            $this->rowWithA('KEY'),

            // row with 2 legend items (Red + Orange)
            $this->rowWithFourCols('', 'NOT YET INVESTIGATED', '', 'INVESTIGATION IN PROGRESS'),

            // row with green item (Complete)
            $this->rowWithFourCols('', 'COMPLETE', null, null),
        ];

        return array_merge(
            [[$title]],
            [$headings],
            $dataRows,
            $keyRows
        );
    }

    private function rowWithA($val): array
    {
        $row = array_fill(0, max(1, count($this->columns)), null);
        $row[0] = $val; // Column A
        return $row;
    }

    /**
     * Put values into A-D while keeping the row length = number of export columns.
     */
    private function rowWithFourCols($a, $b, $c, $d): array
    {
        $row = array_fill(0, max(1, count($this->columns)), null);

        // A-D only if they exist (even if report has < 4 columns, we still place A/B)
        $row[0] = $a;
        if (count($row) > 1) $row[1] = $b;
        if (count($row) > 2) $row[2] = $c;
        if (count($row) > 3) $row[3] = $d;

        return $row;
    }

    // ✅ gray headings + black text, bold, size 11
    public function styles(Worksheet $sheet)
    {
        return [
            2 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => '000000'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'wrapText' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [];

        foreach ($this->columns as $i => $key) {
            $col = Coordinate::stringFromColumnIndex($i + 1);

            $w = 20;

            if (in_array($key, [
                'incident_summary','root_cause','impact','immediate_action',
                'preventive_action','management_comment','latest_review_notes',
                'attachments_names','police_action_plan'
            ], true)) $w = 45;

            if (in_array($key, ['location'], true)) $w = 28;
            if (in_array($key, ['incident_number'], true)) $w = 16;

            if (in_array($key, [
                'status','severity','currency','police_reported','police_required','loss_still_happening',
                'sla_late_logged','sla_close_urgency'
            ], true)) $w = 16;

            if (in_array($key, ['sla_late_by'], true)) $w = 22;
            if (in_array($key, ['sla_days_left_to_close'], true)) $w = 20;

            if (in_array($key, [
                'incident_at','created_at','updated_at','latest_review_at','date_insurance_claim_submitted','claim_number','submitted_by'
            ], true)) $w = 20;

            $widths[$col] = $w;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestColumn = $sheet->getHighestColumn();

                // ✅ Table range ONLY (borders stop here)
                $rangeTable = "A1:{$highestColumn}{$this->dataEndRow}";

                // Title merge only across the table width
                $sheet->mergeCells("A1:{$highestColumn}1");

                // Title style
                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['rgb' => '22345F'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'EEF2F8'],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                // ✅ Font 11 + wrap ONLY for table area
                $sheet->getStyle($rangeTable)->applyFromArray([
                    'font' => [
                        'size' => 11,
                        'color' => ['rgb' => '000000'],
                    ],
                ]);

                $sheet->getStyle($rangeTable)->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getRowDimension(2)->setRowHeight(28);

                for ($row = 3; $row <= $this->dataEndRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }

                // ✅ BLACK borders ONLY for table (KEY is OUTSIDE borders)
                $sheet->getStyle($rangeTable)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->freezePane('A3');
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 2);

                // SLA coloring (only table rows)
                $this->applySlaIndicators($sheet);

                // ✅ Robot ranking (status) strong colors
                $this->applyRobotRankingToStatus($sheet);

                // ✅ KEY (4 columns A-D) below table, no borders
                $this->renderRobotKey($sheet);
            },
        ];
    }

    /**
     * ✅ Robot ranking: STATUS column gets solid strong fill, NO visible text
     * under_review => RED
     * investigating => ORANGE
     * resolved/closed => GREEN
     */
    private function applyRobotRankingToStatus(Worksheet $sheet): void
    {
        $colStatus = $this->findColumnLetter('status');
        if (!$colStatus) return;

        // Strong fills
        $RED    = 'FF0000';
        $ORANGE = 'FFA500';
        $GREEN  = '00B050';

        for ($row = 3; $row <= $this->dataEndRow; $row++) {
            $cell = "{$colStatus}{$row}";
            $raw = (string) $sheet->getCell($cell)->getValue();
            $st = strtolower(trim($raw));

            $fill = null;
            if ($st === 'under_review') $fill = $RED;
            elseif ($st === 'investigating') $fill = $ORANGE;
            elseif ($st === 'resolved' || $st === 'closed') $fill = $GREEN;

            if (!$fill) continue;

            // ✅ color block + hide the text (so it’s just a robot indicator)
            $sheet->getStyle($cell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fill],
                ],
                'font' => [
                    'color' => ['rgb' => $fill], // hide text
                    'bold' => false,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }
    }

    /**
     * ✅ KEY uses 4 columns (A-D) and sits below the bordered table
     * NOT inside borders
     */
    private function renderRobotKey(Worksheet $sheet): void
    {
        $rKeyTitle = $this->keyStartRow;       // "KEY"
        $rRow1     = $this->keyStartRow + 1;   // red + orange
        $rRow2     = $this->keyStartRow + 2;   // green

        // ✅ Make the colour blocks 4x wider
        // A and C are colour blocks (wider now)
        $sheet->getColumnDimension('A')->setWidth(16);  // was 4
        $sheet->getColumnDimension('B')->setWidth(26);  // label
        $sheet->getColumnDimension('C')->setWidth(16);  // was 4
        $sheet->getColumnDimension('D')->setWidth(30);  // label

        // ✅ KEY title merged A:D
        $sheet->mergeCells("A{$rKeyTitle}:D{$rKeyTitle}");
        $sheet->getStyle("A{$rKeyTitle}:D{$rKeyTitle}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => '000000'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
        ]);
        $sheet->getRowDimension($rKeyTitle)->setRowHeight(20);

        // ✅ Strong colour fills
        $sheet->getStyle("A{$rRow1}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF0000']],
        ]);
        $sheet->getStyle("C{$rRow1}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFA500']],
        ]);
        $sheet->getStyle("A{$rRow2}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00B050']],
        ]);

        // ✅ Labels formatting
        $sheet->getStyle("B{$rRow1}:D{$rRow1}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $sheet->getStyle("B{$rRow2}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $sheet->getRowDimension($rRow1)->setRowHeight(20);
        $sheet->getRowDimension($rRow2)->setRowHeight(20);

        // ✅ Wrap the KEY in a BLACK BORDER (A:D, rows keyTitle -> row2)
        $keyRange = "A{$rKeyTitle}:D{$rRow2}";
        $sheet->getStyle($keyRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }


    private function applySlaIndicators(Worksheet $sheet): void
    {
        $colLateLogged = $this->findColumnLetter('sla_late_logged');
        $colUrgency    = $this->findColumnLetter('sla_close_urgency');
        $colDaysLeft   = $this->findColumnLetter('sla_days_left_to_close');

        if (!$colLateLogged && !$colUrgency && !$colDaysLeft) return;

        $redFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEE2E2'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '991B1B'],
            ],
        ];

        $yellowFill = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF9C3'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '854D0E'],
            ],
        ];

        for ($row = 3; $row <= $this->dataEndRow; $row++) {
            if ($colLateLogged) {
                $cell = "{$colLateLogged}{$row}";
                $val = strtoupper(trim((string)$sheet->getCell($cell)->getValue()));
                if ($val === 'YES' || $val === 'TRUE' || $val === '1') {
                    $sheet->getStyle($cell)->applyFromArray($redFill);
                }
            }

            if ($colUrgency) {
                $cell = "{$colUrgency}{$row}";
                $val = strtoupper(trim((string)$sheet->getCell($cell)->getValue()));

                if (str_contains($val, 'OVERDUE')) {
                    $sheet->getStyle($cell)->applyFromArray($redFill);
                } elseif (str_contains($val, 'DUE SOON')) {
                    $sheet->getStyle($cell)->applyFromArray($yellowFill);
                }
            }

            if ($colDaysLeft) {
                $cell = "{$colDaysLeft}{$row}";
                $raw = $sheet->getCell($cell)->getValue();
                $daysLeft = is_numeric($raw) ? (int)$raw : null;

                if ($daysLeft !== null) {
                    if ($daysLeft <= 0) {
                        $sheet->getStyle($cell)->applyFromArray($redFill);
                    } elseif ($daysLeft <= $this->dueSoonDays) {
                        $sheet->getStyle($cell)->applyFromArray($yellowFill);
                    }
                }
            }
        }
    }

    private function findColumnLetter(string $key): ?string
    {
        $idx = array_search($key, $this->columns, true);
        if ($idx === false) return null;
        return Coordinate::stringFromColumnIndex($idx + 1);
    }

    private function buildTitleLine(): string
    {
        $title = 'INCIDENT REGISTER';

        $from = $this->fromInput ? $this->safeDate($this->fromInput) : null;
        $to   = $this->toInput ? $this->safeDate($this->toInput) : null;

        if ($from && $to) return $title . " (From {$from} to {$to})";
        if ($from) return $title . " (From {$from})";
        if ($to) return $title . " (Up to {$to})";

        return $title;
    }

    private function safeDate(string $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private function latestReview($r)
    {
        return collect($r->reportReviews ?? [])->sortByDesc('created_at')->first();
    }

    private function minutesToHuman(?float $minutes): string
    {
        if ($minutes === null) return '';
        $m = (int) round($minutes);
        if ($m <= 0) return '0m';

        $minPerHour  = 60;
        $minPerDay   = 60 * 24;
        $minPerWeek  = $minPerDay * 7;
        $minPerMonth = $minPerDay * 30;

        $mo = intdiv($m, $minPerMonth); $m -= $mo * $minPerMonth;
        $w  = intdiv($m, $minPerWeek);  $m -= $w * $minPerWeek;
        $d  = intdiv($m, $minPerDay);   $m -= $d * $minPerDay;
        $h  = intdiv($m, $minPerHour);  $m -= $h * $minPerHour;
        $mi = $m;

        $parts = [];
        if ($mo) $parts[] = "{$mo}mo";
        if ($w)  $parts[] = "{$w}w";
        if ($d)  $parts[] = "{$d}d";
        if ($h)  $parts[] = "{$h}h";
        if ($mi) $parts[] = "{$mi}m";

        return implode(' ', $parts);
    }

    private function valueFor($r, string $key)
    {
        switch ($key) {
            case 'incident_type': {
                $type = trim((string)($r->incident_type ?? ''));

                $other = trim((string)(
                    $r->other_incident_type
                    ?? $r->incident_type_other
                    ?? data_get($r, 'other_incident_type')
                    ?? data_get($r, 'incident_type_other')
                    ?? ''
                ));

                if (strtolower($type) === 'other' && $other !== '') {
                    return "Other ({$other})";
                }

                return $type;
            }

            case 'reported_by_name':
                $name  = $r->reportedBy->full_name ?? ($r->reported_by['full_name'] ?? $r->reported_by ?? '');
                $email = $r->reportedBy->email ?? ($r->reported_by['email'] ?? '');
                return trim($name . ($email ? "\n" . $email : ''));

            case 'reported_by_email':
                return $r->reportedBy->email ?? ($r->reported_by['email'] ?? '');

            case 'country':
                return $r->country->name ?? '';

            case 'brand':
                return $r->brand->name ?? '';

            case 'loss_still_happening':
            case 'police_required':
            case 'police_reported':
                return ((int)($r->{$key} ?? 0) === 1) ? 'Yes' : 'No';

            case 'police_action_plan':
                return $r->police_action_plan ?? '';

            case 'attachments_count':
                return is_countable($r->attachments ?? []) ? count($r->attachments) : 0;

            case 'attachments_names':
                return collect($r->attachments ?? [])->pluck('file_name')->filter()->join(', ');

            case 'latest_review_status':
                return optional($this->latestReview($r))->status ?? '';

            case 'latest_review_notes':
                return optional($this->latestReview($r))->notes ?? '';

            case 'latest_review_at':
                return optional($this->latestReview($r))->created_at ?? '';

            case 'date_insurance_claim_submitted':
                if (!$r->date_insurance_claim_submitted) return 'N/A';
                try {
                    return Carbon::parse($r->date_insurance_claim_submitted)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return (string)$r->date_insurance_claim_submitted;
                }

            case 'sla_late_logged': {
                $late = data_get($r, 'sla.response.late_logged');
                return $late ? 'Yes' : 'No';
            }

            case 'sla_late_by': {
                $late = data_get($r, 'sla.response.late_logged');
                if (!$late) return '';
                $lateBy = data_get($r, 'sla.response.late_by_minutes');
                if ($lateBy === null) {
                    $lateBy = data_get($r, 'sla.response.response_delay_minutes');
                }
                return $this->minutesToHuman($lateBy);
            }

            case 'sla_days_left_to_close': {
                $val = data_get($r, 'sla.close.days_left_to_close');
                return $val === null ? '' : (int)$val;
            }

            case 'sla_close_urgency': {
                $isClosed = (bool) data_get($r, 'sla.close.is_closed', false);
                if ($isClosed) return 'CLOSED';

                $overdue = (int) data_get($r, 'sla.close.days_overdue', 0);
                $daysLeft = data_get($r, 'sla.close.days_left_to_close');

                if ($overdue > 0) return "OVERDUE ({$overdue}d)";

                if ($daysLeft !== null) {
                    $daysLeft = (int) $daysLeft;
                    if ($daysLeft <= $this->dueSoonDays) {
                        return "DUE SOON ({$daysLeft}d)";
                    }
                    return "OK ({$daysLeft}d)";
                }

                return 'OK';
            }

            default:
                return $r->{$key} ?? '';
        }
    }
}
