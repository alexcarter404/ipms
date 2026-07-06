<?php

namespace App\Actions\Billing;

use App\Models\BillingAgreement;
use App\Models\Matter;
use Illuminate\Support\Facades\DB;

class SaveBillingAgreement
{
    public function handle(Matter $matter, array $data): BillingAgreement
    {
        return DB::transaction(function () use ($matter, $data) {
            $stages = $data['stages'] ?? [];
            unset($data['stages']);

            $agreement = BillingAgreement::updateOrCreate(
                ['matter_id' => $matter->id],
                $data
            );

            // Sync milestone stages: keep ids that came back, drop the rest.
            $keptIds = [];
            foreach (array_values($stages) as $index => $stage) {
                $saved = $agreement->stages()->updateOrCreate(
                    ['id' => $stage['id'] ?? null],
                    [
                        'description' => $stage['description'],
                        'amount' => $stage['amount'],
                        'sort_order' => $index,
                    ]
                );
                $keptIds[] = $saved->id;
            }
            $agreement->stages()->whereNotIn('id', $keptIds)->delete();

            return $agreement->load('stages');
        });
    }
}
