<?php

namespace App\Http\Controllers;

use App\Enums\MatterType;
use App\Http\Requests\CommTemplateRequest;
use App\Http\Requests\TemplatePreviewRequest;
use App\Models\CommTemplate;
use App\Models\Matter;
use App\Repositories\CommTemplateRepository;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CommTemplateController extends Controller
{
    public function index(CommTemplateRepository $templates): Response
    {
        return Inertia::render('Templates/Index', [
            'templates' => $templates->allWithUsage(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Templates/Create', [
            'types' => MatterType::options(),
            'mergeFields' => TemplateRenderer::availableFields(),
        ]);
    }

    public function store(CommTemplateRequest $request): RedirectResponse
    {
        CommTemplate::create($request->validated());

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

    public function update(CommTemplateRequest $request, CommTemplate $template): RedirectResponse
    {
        $template->update($request->validated());

        return redirect()->route('templates.index')->with('success', 'Template updated.');
    }

    public function destroy(CommTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
    }

    /** Preview a template rendered against a matter (used by the comm composer). */
    public function preview(TemplatePreviewRequest $request, TemplateRenderer $renderer): JsonResponse
    {
        $data = $request->validated();

        $template = CommTemplate::findOrFail($data['template_id']);
        $matter = Matter::findOrFail($data['matter_id']);

        return response()->json(
            $renderer->render($template, $matter) + ['channel' => $template->channel]
        );
    }
}
