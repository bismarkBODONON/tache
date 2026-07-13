<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // Liste des projets créés par l'utilisateur connecté
    public function index(Request $request)
    {
        return Project::where('created_by', $request->user()->id)
            ->withCount('tasks')
            ->latest()
            ->get();
    }

    // Créer un projet
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($project, 201);
    }

    // Détail d'un projet avec ses tâches
    public function show(Request $request, Project $project)
    {
        abort_unless($project->created_by === $request->user()->id, 403, 'Accès refusé.');

        return $project->load(['tasks.assignee', 'tasks.creator']);
    }

    // Modifier un projet
    public function update(Request $request, Project $project)
    {
        abort_unless($project->created_by === $request->user()->id, 403, 'Accès refusé.');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $project->update($data);

        return response()->json($project);
    }

    // Supprimer un projet (les tâches deviennent "sans projet" grâce à nullOnDelete)
    public function destroy(Request $request, Project $project)
    {
        abort_unless($project->created_by === $request->user()->id, 403, 'Accès refusé.');

        $project->delete();

        return response()->json(null, 204);
    }
}