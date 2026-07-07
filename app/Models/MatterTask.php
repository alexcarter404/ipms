<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MatterTask extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'matter_tasks';

    protected $fillable = [
        'matter_id', 'workflow_step_id', 'title', 'description', 'due_date',
        'internal_date', 'is_critical', 'priority', 'status', 'assigned_to',
        'created_by', 'completed_at', 'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'internal_date' => 'date',
            'is_critical' => 'boolean',
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', now());
    }
}
