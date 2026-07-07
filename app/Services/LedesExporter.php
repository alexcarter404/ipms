<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TimeEntry;

/**
 * LEDES 1998B — the e-billing lingua franca corporate legal
 * departments ingest. Pipe-delimited, one row per invoice line, fees
 * (F) and expenses (E) typed per the billable behind the line.
 */
class LedesExporter
{
    private const FIELDS = [
        'INVOICE_DATE', 'INVOICE_NUMBER', 'CLIENT_ID', 'LAW_FIRM_MATTER_ID',
        'INVOICE_TOTAL', 'BILLING_START_DATE', 'BILLING_END_DATE',
        'INVOICE_DESCRIPTION', 'LINE_ITEM_NUMBER', 'EXP/FEE/INV_ADJ_TYPE',
        'LINE_ITEM_NUMBER_OF_UNITS', 'LINE_ITEM_ADJUSTMENT_AMOUNT',
        'LINE_ITEM_TOTAL', 'LINE_ITEM_DATE', 'LINE_ITEM_TASK_CODE',
        'LINE_ITEM_EXPENSE_CODE', 'LINE_ITEM_ACTIVITY_CODE', 'TIMEKEEPER_ID',
        'LINE_ITEM_DESCRIPTION', 'LAW_FIRM_ID', 'LINE_ITEM_UNIT_COST',
        'TIMEKEEPER_NAME', 'TIMEKEEPER_CLASSIFICATION', 'CLIENT_MATTER_ID',
    ];

    public function export(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines.billable', 'lines.matter', 'client', 'entity']);

        $rows = ["LEDES1998B[]", implode('|', self::FIELDS).'[]'];
        $invoiceDate = $invoice->issued_at?->format('Ymd') ?? now()->format('Ymd');
        $lineDates = $invoice->lines
            ->map(fn ($line) => $this->lineDate($line))
            ->filter()
            ->sort()
            ->values();

        foreach ($invoice->lines as $index => $line) {
            $billable = $line->billable;
            $isTime = $billable instanceof TimeEntry;
            $isAdjustment = $line->line_total < 0;

            $rows[] = implode('|', [
                $invoiceDate,
                $invoice->displayNumber(),
                (string) $invoice->client_id,
                $line->matter?->reference ?? '',
                $this->amount($invoice->total),
                ($lineDates->first() ?? $invoiceDate),
                ($lineDates->last() ?? $invoiceDate),
                'Professional services and disbursements',
                (string) ($index + 1),
                $isAdjustment ? ($isTime ? 'IF' : 'IE') : ($isTime ? 'F' : 'E'),
                $this->amount($line->quantity),
                $isAdjustment ? $this->amount($line->line_total) : '0.00',
                $this->amount($line->line_total),
                $this->lineDate($line) ?? $invoiceDate,
                $isTime ? ($billable->activityCode?->code ?? '') : '',
                $isTime ? '' : 'E101',
                $isTime ? ($billable->activityCode?->code ?? '') : '',
                $isTime ? (string) $billable->user_id : '',
                $this->clean($line->description),
                'IPMS-LLP',
                $this->amount($line->unit_amount),
                $isTime ? ($billable->user?->name ?? '') : '',
                $isTime ? strtoupper($billable->user?->role?->value ?? '') : '',
                $line->matter?->reference ?? '',
            ]).'[]';
        }

        return implode("\n", $rows)."\n";
    }

    private function lineDate($line): ?string
    {
        $billable = $line->billable;

        $date = $billable?->work_date ?? $billable?->date ?? null;

        return $date?->format('Ymd');
    }

    private function amount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function clean(?string $value): string
    {
        return str_replace(['|', "\n", "\r"], [' ', ' ', ' '], (string) $value);
    }
}
