<?php

namespace App\Actions\Billing;

use App\Enums\AgreementType;
use App\Exceptions\DomainActionException;
use App\Models\BillingAgreement;
use App\Models\ClientEntity;
use App\Models\Matter;
use Illuminate\Support\Facades\DB;

class SaveBillingAgreement
{
    public function handle(Matter $matter, array $data): BillingAgreement
    {
        return $this->save(['matter_id' => $matter->id], $data);
    }

    /** The entity-level default handed down to its matters. */
    public function forEntity(ClientEntity $entity, array $data): BillingAgreement
    {
        if (($data['type'] ?? null) === AgreementType::Stage->value) {
            throw new DomainActionException(
                'Stage payments are milestone-specific — set them on the matter, not the entity default.'
            );
        }

        return $this->save(['client_entity_id' => $entity->id], $data);
    }

    private function save(array $owner, array $data): BillingAgreement
    {
        return DB::transaction(function () use ($owner, $data) {
            $stages = $data['stages'] ?? [];
            unset($data['stages']);

            $agreement = BillingAgreement::updateOrCreate($owner, $data);

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
