<?php

namespace App\Http\Controllers;

use App\Actions\Audits\TransitionAudit;
use App\Exceptions\DomainActionException;
use Illuminate\Http\RedirectResponse;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    public function transition(Audit $audit, TransitionAudit $action): RedirectResponse
    {
        try {
            $action->handle($audit);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'State restored — the record now carries the values from this entry.');
    }
}
