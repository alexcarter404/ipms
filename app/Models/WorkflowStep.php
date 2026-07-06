<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'title', 'description', 'offset_value',
        'offset_unit', 'is_critical', 'required_fields', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean',
            'required_fields' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function dueDateFrom(CarbonInterface $base): CarbonInterface
    {
        $date = $base->copy();

        return match ($this->offset_unit) {
            'weeks' => $date->addWeeks($this->offset_value),
            'months' => $date->addMonths($this->offset_value),
            'years' => $date->addYears($this->offset_value),
            default => $date->addDays($this->offset_value),
        };
    }
}
