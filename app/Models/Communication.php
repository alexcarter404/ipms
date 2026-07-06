<?php

namespace App\Models;

use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory;

    protected $fillable = [
        'matter_id', 'comm_template_id', 'channel', 'direction', 'from_name',
        'from_email', 'recipient_name', 'recipient_email', 'subject', 'body',
        'status', 'sent_at', 'received_at', 'external_id', 'attachments',
        'created_by',
    ];

    // Raw attachment payloads are plumbing, not history
    protected $auditExclude = ['attachments'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommTemplate::class, 'comm_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
