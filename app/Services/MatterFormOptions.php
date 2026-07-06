<?php

namespace App\Services;

use App\Enums\MatterStatus;
use App\Enums\MatterType;
use App\Repositories\ClientRepository;
use App\Repositories\FamilyRepository;
use App\Repositories\MatterRepository;
use App\Repositories\UserRepository;
use App\Support\Countries;

/** Everything the matter create/edit/take-on forms need to render. */
class MatterFormOptions
{
    public function __construct(
        private ClientRepository $clients,
        private FamilyRepository $families,
        private UserRepository $users,
        private MatterRepository $matters,
    ) {
    }

    public function build(): array
    {
        return [
            'types' => MatterType::options(),
            'statuses' => MatterStatus::options(),
            'countries' => Countries::options(),
            'clients' => $this->clients->optionsWithEntities(),
            'families' => $this->families->options(),
            'users' => $this->users->options(),
            'matters' => $this->matters->referenceOptions(),
            'filingRoutes' => [
                ['value' => 'national', 'label' => 'National'],
                ['value' => 'pct', 'label' => 'PCT'],
                ['value' => 'ep', 'label' => 'European Patent (EP)'],
                ['value' => 'madrid', 'label' => 'Madrid Protocol'],
                ['value' => 'hague', 'label' => 'Hague System'],
                ['value' => 'paris', 'label' => 'Paris Convention'],
            ],
        ];
    }
}
