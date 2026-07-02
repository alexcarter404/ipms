<?php

namespace App\Http\Controllers;

use App\Enums\MatterType;
use App\Models\CommTemplate;
use App\Models\Matter;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Templates/Index', [
            'templates' => CommTemplate::withCount('communications')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Templates/Create', [
            'types' => MatterType::options(),
            'mergeFields' => TemplateRenderer::availableFields(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CommTemplate::create($this->validated($request));

        return redirect()->route('templates.index')->with('success', 'Template created.');
    }

    public function edit(CommTemplate $template): Response
    {
        return Inertia::render('Templates/Edit', [
            'template' => $template,
            'types' => MatterType::options(),
            'mergeFields' => TemplateRenderer::availableFields(),
        ]);
    }

    public function update(Request $request, CommTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request));

        return redirect()->route('templates.index')->with('success', 'Template updated.');
    }

    public function destroy(CommTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
    }

    /** Preview a template rendered against a matter (used by the comm composer). */
    public function preview(Request $request, TemplateRenderer $renderer): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'exists:comm_templates,id'],
            'matter_id' => ['required', 'exists:matters,id'],
        ]);

        $template = CommTemplate::findOrFail($data['template_id']);
        $matter = Matter::findOrFail($data['matter_id']);

        return response()->json(
            $renderer->render($template, $matter) + ['channel' => $template->channel]
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'letter'])],
            'matter_type' => ['nullable', Rule::enum(MatterType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
