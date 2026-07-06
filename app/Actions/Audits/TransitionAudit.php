<?php

namespace App\Actions\Audits;

use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Exceptions\AuditableTransitionException;
use OwenIt\Auditing\Models\Audit;

/**
 * Version-history semantics on the audit trail: every entry captures
 * the state it left the record in (a created entry holds the original
 * values, an update entry the values it produced). Restoring applies
 * that captured state via the package's transitionTo — direction is
 * the timeline's problem, not the user's. The restore itself is saved
 * — and therefore audited — so the trail always shows who moved the
 * record and where to.
 */
class TransitionAudit
{
    public function handle(Audit $audit): Model
    {
        if (empty($audit->new_values)) {
            throw new DomainActionException("This entry doesn't carry a restorable state.");
        }

        $auditable = $audit->auditable;

        if (! $auditable) {
            throw new DomainActionException('The audited record no longer exists — nothing to restore.');
        }

        try {
            $auditable->transitionTo($audit);
        } catch (AuditableTransitionException $e) {
            throw new DomainActionException("Can't restore this state: {$e->getMessage()}");
        }

        // transitionTo() only fills the attributes; persisting is on us
        $auditable->save();

        return $auditable;
    }
}
