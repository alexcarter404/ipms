<?php

namespace App\Models;

use App\Enums\MatterType;
use App\Enums\TriggerEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
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
}
