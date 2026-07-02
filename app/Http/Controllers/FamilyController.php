<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /** Quick-create a family (used inline from the matter form). */
    public function store(Request $request): RedirectResponse
    {
        Family::create($request->validate([
            'reference' => ['required', 'string', 'max:30', 'unique:families,reference'],
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Family created.');
    }
}
