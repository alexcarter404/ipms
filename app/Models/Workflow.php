<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use App\Enums\MatterType;
use App\Enums\TriggerEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $fillable = [
        'name', 'matter_type', 'trigger_event', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'matter_type' => MatterType::class,
            'trigger_event' => TriggerEvent::class,
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order');
    }

    /**
     * The data contract for entering the workflow at a stage: the union
     * of every step's required fields up to and including that step —
     * being at a stage presumes the data of all earlier stages.
     *
     * @return list<string>
     */
    public function contractUpTo(WorkflowStep $entryStep): array
    {
        return $this->steps
            ->filter(fn (WorkflowStep $step) => $step->sort_order <= $entryStep->sort_order)
            ->flatMap(fn (WorkflowStep $step) => $step->required_fields ?? [])
            ->unique()
            ->values()
            ->all();
    }
}
