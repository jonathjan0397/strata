<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    private const TRIGGERS = [
        'invoice.created',
        'invoice.paid',
        'invoice.overdue',
        'service.created',
        'service.suspended',
        'service.cancelled',
        'ticket.opened',
        'ticket.closed',
        'client.registered',
    ];

    private const OPERATORS = ['eq', 'neq', 'gt', 'lt', 'gte', 'lte', 'contains'];

    private const ACTION_TYPES = [
        'send.email',
        'create.ticket',
        'suspend.service',
        'add.credit',
        'call.webhook',
    ];

    public function index(): Response
    {
        $workflows = Workflow::withCount(['conditions', 'actions', 'runs'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Workflows/Index', [
            'workflows' => $workflows,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Workflows/Edit', [
            'workflow' => null,
            'triggers' => self::TRIGGERS,
            'operators' => self::OPERATORS,
            'actionTypes' => self::ACTION_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $workflow = Workflow::create([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->syncConditionsAndActions($workflow, $data);

        return redirect()->route('admin.workflows.index')
            ->with('success', 'Workflow created.');
    }

    public function edit(Workflow $workflow): Response
    {
        $workflow->load(['conditions', 'actions']);

        return Inertia::render('Admin/Workflows/Edit', [
            'workflow' => $workflow,
            'triggers' => self::TRIGGERS,
            'operators' => self::OPERATORS,
            'actionTypes' => self::ACTION_TYPES,
        ]);
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $this->validated($request);

        $workflow->update([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->syncConditionsAndActions($workflow, $data);

        return redirect()->route('admin.workflows.index')
            ->with('success', 'Workflow updated.');
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $workflow->delete();

        return redirect()->route('admin.workflows.index')
            ->with('success', 'Workflow deleted.');
    }

    public function toggleActive(Workflow $workflow): RedirectResponse
    {
        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back()->with('success', 'Workflow '.($workflow->is_active ? 'activated' : 'deactivated').'.');
    }

    // -----------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'in:'.implode(',', self::TRIGGERS)],
            'is_active' => ['boolean'],
            'conditions' => ['array'],
            'conditions.*.field' => ['required', 'string', 'max:100'],
            'conditions.*.operator' => ['required', 'string', 'in:'.implode(',', self::OPERATORS)],
            'conditions.*.value' => ['required', 'string', 'max:255'],
            'conditions.*.sort_order' => ['integer', 'min:0'],
            'actions' => ['array'],
            'actions.*.type' => ['required', 'string', 'in:'.implode(',', self::ACTION_TYPES)],
            'actions.*.config' => ['array'],
            'actions.*.delay_minutes' => ['integer', 'min:0'],
            'actions.*.sort_order' => ['integer', 'min:0'],
        ]);
    }

    private function syncConditionsAndActions(Workflow $workflow, array $data): void
    {
        $workflow->conditions()->delete();
        foreach ($data['conditions'] ?? [] as $i => $c) {
            $workflow->conditions()->create([
                'field' => $c['field'],
                'operator' => $c['operator'],
                'value' => $c['value'],
                'sort_order' => $c['sort_order'] ?? $i,
            ]);
        }

        $workflow->actions()->delete();
        foreach ($data['actions'] ?? [] as $i => $a) {
            $workflow->actions()->create([
                'type' => $a['type'],
                'config' => $a['config'] ?? [],
                'delay_minutes' => $a['delay_minutes'] ?? 0,
                'sort_order' => $a['sort_order'] ?? $i,
            ]);
        }
    }
}
