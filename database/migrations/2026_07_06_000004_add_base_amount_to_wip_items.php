<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every WIP item stores its value in BOTH the billing currency
     * (amount) and the firm's base currency (base_amount), converted at
     * capture time — so base-currency totals never drift with FX moves.
     */
    public function up(): void
    {
        foreach (['time_entries', 'disbursements', 'charges'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('base_amount', 12, 2)->nullable()->after('amount');
            });

            $this->backfill($tableName);

            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('base_amount', 12, 2)->nullable(false)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['time_entries', 'disbursements', 'charges'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('base_amount');
            });
        }
    }

    private function backfill(string $tableName): void
    {
        $base = config('billing.base_currency');

        // Items already in the base currency map 1:1.
        DB::table($tableName)->where('currency_code', $base)
            ->update(['base_amount' => DB::raw('amount')]);

        // Others convert at the latest stored rate per currency.
        foreach (DB::table($tableName)->whereNull('base_amount')
            ->distinct()->pluck('currency_code') as $currency) {
            $rate = DB::table('exchange_rates')
                ->where('currency_code', $currency)
                ->orderByDesc('rate_date')
                ->value('rate');

            DB::table($tableName)->where('currency_code', $currency)
                ->whereNull('base_amount')
                ->update(['base_amount' => $rate
                    ? DB::raw("round(amount / {$rate}, 2)")
                    : DB::raw('amount'), // no rate known — best effort 1:1
                ]);
        }
    }
};
