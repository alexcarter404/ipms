<?php

namespace App\Http\Controllers;

use App\Models\Matter;
use App\Models\MatterClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatterClassController extends Controller
{
    public function store(Request $request, Matter $matter): RedirectResponse
    {
        $data = $request->validate([
            'class_number' => [
                'required', 'integer', 'between:1,45',
                Rule::unique('matter_classes')->where('matter_id', $matter->id),
            ],
            'specification' => ['nullable', 'string'],
        ]);

        $matter->classes()->create($data);

        return back()->with('success', 'Class added.');
    }

    public function update(Request $request, MatterClass $class): RedirectResponse
    {
        $data = $request->validate([
            'specification' => ['nullable', 'string'],
        ]);

        $class->update($data);

        return back()->with('success', 'Class updated.');
    }

    public function destroy(MatterClass $class): RedirectResponse
    {
        $class->delete();

        return back()->with('success', 'Class removed.');
    }
}
