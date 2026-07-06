<?php

namespace App\Models;

use App\Enums\OfficeEventType;
use App\Enums\OfficeMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inbound communication from an IP office (grant, publication,
 * office action, …), matched to a matter and processed by the
 * automation pipeline — with an audit log of everything it did.
 */
class OfficeMessage extends Model
{
    protected $fillable = [
        'office', 'external_id', 'event_type', 'application_no',
        'registration_no', 'event_date', 'summary', 'payload', 'matter_id',
        'status', 'actions', 'error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => OfficeEventType::class,
            'status' => OfficeMessageStatus::class,
            'event_date' => 'date',
            'payload' => 'array',
            'actions' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function officeName(): string
    {
        return config("integrations.offices.{$this->office}.name", strtoupper($this->office));
    }
}
