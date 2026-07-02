<?php

namespace App\Http\Controllers;

use App\Http\Requests\FamilyRequest;
use App\Models\Family;
use Illuminate\Http\RedirectResponse;

class FamilyController extends Controller
{
    /** Quick-create a family (used inline from the matter form). */
    public function store(FamilyRequest $request): RedirectResponse
    {
        Family::create($request->validated());

        return back()->with('success', 'Family created.');
    }
}
