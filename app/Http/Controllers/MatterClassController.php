<?php

namespace App\Http\Controllers;

use App\Http\Requests\MatterClassRequest;
use App\Models\Matter;
use App\Models\MatterClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatterClassController extends Controller
{
    public function store(MatterClassRequest $request, Matter $matter): RedirectResponse
    {
        $matter->classes()->create($request->validated());

        return back()->with('success', 'Class added.');
    }

    public function update(Request $request, MatterClass $class): RedirectResponse
    {
        $class->update($request->validate([
            'specification' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Class updated.');
    }

    public function destroy(MatterClass $class): RedirectResponse
    {
        $class->delete();

        return back()->with('success', 'Class removed.');
    }
}
