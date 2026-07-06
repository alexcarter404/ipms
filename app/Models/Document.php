<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A file on the docket: uploaded by a user, auto-filed from an office
 * exchange message, or generated from a communication template. New
 * versions supersede old ones through parent_id while the chain stays
 * downloadable.
 */
class Document extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'matter_id', 'linked_type', 'linked_id', 'title', 'category',
        'source', 'filename', 'path', 'mime', 'size', 'version',
        'parent_id', 'uploaded_by',
    ];

    // The storage path is plumbing, not history
    protected $auditExclude = ['path'];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function linked(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Latest versions only — superseded revisions stay reachable via history. */
    public function scopeCurrent($query)
    {
        return $query->whereNotIn('id', self::query()->whereNotNull('parent_id')->select('parent_id'));
    }
}
