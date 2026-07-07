<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use App\Services\ReportRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    private const FILTER_RULES = [
        'filters.client_id' => ['nullable', 'exists:clients,id'],
        'filters.user_id' => ['nullable', 'exists:users,id'],
        'filters.status' => ['nullable', 'string', 'max:40'],
        'filters.from' => ['nullable', 'date'],
        'filters.to' => ['nullable', 'date'],
    ];

    public function index(Request $request, ClientRepository $clients, UserRepository $users, ReportRunner $runner): Response
    {
        // A run is a GET with query params so results are linkable
        $results = null;
        $running = $request->only('type', 'filters');
        if ($request->filled('type') && array_key_exists($request->type, ReportRunner::TYPES)) {
            $results = $runner->run($request->type, (array) $request->input('filters', []));
        }

        return Inertia::render('Reports/Index', [
            'types' => collect(ReportRunner::TYPES)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'clients' => $clients->options(),
            'users' => $users->options(),
            'saved' => Report::with('creator:id,name')->latest()->get()->map(fn (Report $report) => [
                'id' => $report->id,
                'name' => $report->name,
                'type' => $report->type,
                'type_label' => ReportRunner::TYPES[$report->type] ?? $report->type,
                'filters' => $report->filters ?? [],
                'schedule' => $report->schedule,
                'creator' => $report->creator?->name,
                'last_run_at' => $report->last_run_at?->toDateTimeString(),
            ]),
            'running' => $running,
            'results' => $results,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(ReportRunner::TYPES))],
            'filters' => ['nullable', 'array'],
            'schedule' => ['nullable', Rule::in(['daily', 'weekly'])],
        ] + self::FILTER_RULES);

        Report::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'filters' => array_filter($data['filters'] ?? []),
            'schedule' => $data['schedule'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Report “{$data['name']}” saved.");
    }

    public function csv(Request $request, ReportRunner $runner)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportRunner::TYPES))],
            'filters' => ['nullable', 'array'],
        ] + self::FILTER_RULES);

        $csv = $runner->toCsv($runner->run($data['type'], (array) ($data['filters'] ?? [])));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$data['type'].'-report.csv"',
        ]);
    }

    public function destroy(Report $report): RedirectResponse
    {
        $report->delete();

        return back()->with('success', 'Report deleted.');
    }
}
