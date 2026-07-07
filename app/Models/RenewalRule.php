<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\MatterType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RenewalRule extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name', 'matter_type', 'country_code', 'base_date',
        'start_cycle', 'end_cycle', 'interval_years', 'offsets_months',
        'grace_months', 'default_official_fee', 'default_service_fee',
        'currency', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'matter_type' => MatterType::class,
            'offsets_months' => 'array',
            'default_official_fee' => Money::class,
            'default_service_fee' => Money::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Compute the schedule from a base date.
     *
     * @return array<int, CarbonInterface> cycle number => due date
     */
    public function schedule(CarbonInterface $base): array
    {
        $dates = [];

        if (! is_null($this->offsets_months)) {
            foreach (array_values($this->offsets_months) as $i => $months) {
                $dates[$i + 1] = $base->copy()->addMonths((int) $months);
            }

            return $dates;
        }

        if (! $this->start_cycle || ! $this->end_cycle || ! $this->interval_years) {
            return $dates;
        }

        foreach (range($this->start_cycle, $this->end_cycle) as $cycle) {
            $dates[$cycle] = $base->copy()->addYears($cycle * $this->interval_years);
        }

        return $dates;
    }

    /** Which matter date the schedule anchors on. */
    public function baseDateFor(Matter $matter): ?CarbonInterface
    {
        return $this->base_date === 'registration'
            ? $matter->registration_date
            : $matter->application_date;
    }

    public function baseDateLabel(): string
    {
        return $this->base_date === 'registration'
            ? 'registration / grant date'
            : 'filing date';
    }

    /** Human-readable one-line schedule description for the UI. */
    public function summary(): string
    {
        $anchor = $this->base_date === 'registration' ? 'from grant/registration' : 'from filing';

        if (! is_null($this->offsets_months)) {
            if (! count($this->offsets_months)) {
                return 'No renewals for this right';
            }

            $points = implode(', ', array_map(
                fn ($m) => rtrim(rtrim(number_format($m / 12, 1), '0'), '.').'y',
                $this->offsets_months
            ));

            return "Due at {$points} {$anchor}";
        }

        $interval = $this->interval_years === 1 ? 'yearly' : "every {$this->interval_years} years";

        return "Cycles {$this->start_cycle}–{$this->end_cycle}, {$interval} {$anchor}";
    }
}
