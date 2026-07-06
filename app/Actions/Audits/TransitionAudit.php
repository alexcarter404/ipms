<?php

namespace App\Actions\Audits;

use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Exceptions\AuditableTransitionException;
use OwenIt\Auditing\Models\Audit;

/**
 * Time-travel on an audit entry: roll the record back to the values it
 * had before the change, or forward to the values the change produced.
 * The transition itself is saved — and therefore audited — so the trail
 * always shows who moved the record and where to.
 */
class TransitionAudit
{
    public function handle(Audit $audit, string $direction): Model
    {
        if ($audit->event !== 'updated') {
            throw new DomainActionException('Only update entries carry a before/after state to travel between.');
        }

        $auditable = $audit->auditable;

        if (! $auditable) {
            throw new DomainActionException('The audited record no longer exists — nothing to restore.');
        }

        try {
            $auditable->transitionTo($audit, $direction === 'back');
        } catch (AuditableTransitionException $e) {
            throw new DomainActionException("Can't restore this state: {$e->getMessage()}");
        }

        // transitionTo() only fills the attributes; persisting is on us
        $auditable->save();

        return $auditable;
    }
}
