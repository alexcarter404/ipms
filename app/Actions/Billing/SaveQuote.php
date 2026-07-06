<?php

namespace App\Actions\Billing;

use App\Enums\QuoteStatus;
use App\Exceptions\DomainActionException;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Repositories\QuoteRepository;
use Illuminate\Support\Facades\DB;

class SaveQuote
{
    public function __construct(private QuoteRepository $quotes)
    {
    }

    public function create(array $data): Quote
    {
        return DB::transaction(function () use ($data) {
            $quote = new Quote(['quote_no' => $this->quotes->nextNumber(), 'status' => QuoteStatus::Draft]);

            return $this->fill($quote, $data);
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new DomainActionException('Only draft quotes can be edited.');
        }

        return DB::transaction(fn () => $this->fill($quote, $data));
    }

    private function fill(Quote $quote, array $data): Quote
    {
        $lines = $data['lines'];
        unset($data['lines']);

        $taxRate = isset($data['tax_rate_id']) ? TaxRate::find($data['tax_rate_id']) : null;
        unset($data['tax_rate_id']);

        $quote->fill($data + [
            'tax_name' => $taxRate?->name,
            'tax_pct' => $taxRate?->rate ?? 0,
        ])->save();

        $quote->lines()->delete();
        $subtotal = 0.0;

        foreach (array_values($lines) as $index => $line) {
            $total = round((float) $line['quantity'] * (float) $line['unit_amount'], 2);
            $subtotal += $total;
            $quote->lines()->create([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_amount' => $line['unit_amount'],
                'line_total' => $total,
                'sort_order' => $index,
            ]);
        }

        $taxAmount = round($subtotal * (float) $quote->tax_pct / 100, 2);
        $quote->update([
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount, 2),
        ]);

        return $quote->load('lines');
    }
}
