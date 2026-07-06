<?php

namespace App\Http\Controllers;

use App\Actions\Audits\TransitionAudit;
use App\Exceptions\DomainActionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OwenIt\Auditing\Models\Audit;

class AuditController extends Controller
{
    public function transition(Request $request, Audit $audit, TransitionAudit $action): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['back', 'forward'])],
        ]);

        try {
            $action->handle($audit, $data['direction']);
        } catch (DomainActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['direction'] === 'back'
            ? 'Rolled back — the record now carries the values from before this change.'
            : 'Rolled forward — the record now carries the values this change produced.');
    }
}
