<?php

namespace App\Actions\Matters;

use App\Models\Matter;

class SaveMatter
{
    public function create(array $data): Matter
    {
        return Matter::create($data);
    }

    public function update(Matter $matter, array $data): Matter
    {
        $matter->update($data);

        return $matter;
    }
}
