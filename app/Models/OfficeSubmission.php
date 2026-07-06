<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An outbound package to an IP office — a filing, office action
 * response, renewal payment or document — built from matter data,
 * pushed through the office connector, and acknowledged with the
 * office's receipt reference.
 */
class OfficeSubmission extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'office', 'matter_id', 'task_id', 'submission_type', 'payload',
        'notes', 'status', 'external_ref', 'receipt', 'error', 'created_by',
        'submitted_at', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_type' => SubmissionType::class,
            'status' => SubmissionStatus::class,
            'payload' => 'array',
            'receipt' => 'array',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(MatterTask::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function officeName(): string
    {
        return config("integrations.offices.{$this->office}.name", strtoupper($this->office));
    }
}
