<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // Cas d'utilisation : Rechercher tâche
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $query = Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('assigned_to', $userId)
                    ->orWhereNull('assigned_to');
            });

        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->q.'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->boolean('no_project')) {
            $query->whereNull('project_id');
        }

        return $query->with(['assignee', 'creator', 'project'])->latest()->get();
    }

    // Cas d'utilisation : Créer une tâche
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $task = Task::create([
            ...$data,
            'created_by' => $request->user()->id,
            'status' => 'a_faire',
        ]);

        return response()->json($task->load(['assignee', 'creator', 'project']), 201);
    }

    public function show(Task $task)
    {
        return $task->load(['assignee', 'creator', 'project']);
    }

    // Cas d'utilisation : Modifier tâche
    public function update(Request $request, Task $task)
    {
        $this->authorizeAccess($task, $request);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $task->update($data);

        return response()->json($task->load(['assignee', 'creator', 'project']));
    }

    // Cas d'utilisation : Supprimer tâche
    public function destroy(Request $request, Task $task)
    {
        abort_unless($task->created_by === $request->user()->id, 403, 'Accès refusé.');
        $task->delete();

        return response()->json(null, 204);
    }

    // Cas d'utilisation : Assigner une tâche
    public function assign(Request $request, Task $task)
    {
        $userId = $request->user()->id;
        $isCreator = $task->created_by === $userId;
        $isUnassigned = $task->assigned_to === null;

        // Le créateur peut assigner à qui il veut.
        // N'importe qui peut s'auto-assigner une tâche non assignée.
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $isSelfClaim = $isUnassigned && (int) $data['assigned_to'] === $userId;

        abort_unless($isCreator || $isSelfClaim, 403, 'Accès refusé.');

        $task->update(['assigned_to' => $data['assigned_to']]);

        return response()->json($task->load(['assignee', 'creator', 'project']));
    }

    // Cas d'utilisation : Changer le statut d'une tâche
    public function changeStatus(Request $request, Task $task)
    {
        $this->authorizeAccess($task, $request);

        $data = $request->validate([
            'status' => ['required', 'in:a_faire,en_cours,termine'],
        ]);

        $task->update(['status' => $data['status']]);

        return response()->json($task->load(['assignee', 'creator', 'project']));
    }

    private function authorizeAccess(Task $task, Request $request): void
    {
        $isCreator = $task->created_by === $request->user()->id;
        $isAssignee = $task->assigned_to === $request->user()->id;

        abort_unless($isCreator || $isAssignee, 403, 'Accès refusé.');
    }
}