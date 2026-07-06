<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monetary columns move from decimal(…,2) to integer minor units
 * (fixed-point hundredths). Decimals are exact in MySQL but arrive in
 * PHP as strings and become floats in arithmetic — and on SQLite they
 * are floats at rest. Integers are exact everywhere, and SUM() over
 * them can't drift. Ratios (tax/markup percentages, FX rates,
 * quantities) are not money and keep their decimal types.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array{nullable?: bool, default?: int}>> */
    private const MONEY_COLUMNS = [
        'renewals' => [
            'official_fee' => ['nullable' => true],
            'service_fee' => ['nullable' => true],
        ],
        'renewal_rules' => [
            'default_official_fee' => ['nullable' => true],
            'default_service_fee' => ['nullable' => true],
        ],
        'billing_agreements' => [
            'blended_rate' => ['nullable' => true],
            'cap_amount' => ['nullable' => true],
            'fixed_amount' => ['nullable' => true],
        ],
        'billing_agreement_stages' => [
            'amount' => [],
        ],
        'rate_cards' => [
            'hourly_rate' => [],
        ],
        'time_entries' => [
            'rate' => [],
            'amount' => [],
            'base_amount' => ['default' => 0],
        ],
        'disbursements' => [
            'cost_amount' => [],
            'amount' => [],
            'base_amount' => ['default' => 0],
        ],
        'charges' => [
            'amount' => [],
            'base_amount' => ['default' => 0],
        ],
        'invoices' => [
            'subtotal' => ['default' => 0],
            'tax_amount' => ['default' => 0],
            'total' => ['default' => 0],
        ],
        'invoice_lines' => [
            'unit_amount' => [],
            'line_total' => [],
        ],
        'payments' => [
            'amount' => [],
        ],
        'quotes' => [
            'subtotal' => ['default' => 0],
            'tax_amount' => ['default' => 0],
            'total' => ['default' => 0],
        ],
        'quote_lines' => [
            'unit_amount' => [],
            'line_total' => [],
        ],
        'budgets' => [
            'amount' => [],
            'base_amount' => [],
        ],
    ];

    public function up(): void
    {
        $this->convert('ROUND(%s * 100)');

        foreach (self::MONEY_COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column => $options) {
                    $definition = $blueprint->bigInteger($column);
                    if ($options['nullable'] ?? false) {
                        $definition->nullable();
                    }
                    if (array_key_exists('default', $options)) {
                        $definition->default($options['default']);
                    }
                    $definition->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column => $options) {
                    $definition = $blueprint->decimal($column, 14, 2);
                    if ($options['nullable'] ?? false) {
                        $definition->nullable();
                    }
                    if (array_key_exists('default', $options)) {
                        $definition->default($options['default']);
                    }
                    $definition->change();
                }
            });
        }

        $this->convert('ROUND(%s * 0.01, 2)');
    }

    private function convert(string $expression): void
    {
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                DB::table($table)->whereNotNull($column)->update([
                    $column => DB::raw(sprintf($expression, $column)),
                ]);
            }
        }
    }
};
